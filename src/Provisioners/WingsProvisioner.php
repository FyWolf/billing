<?php

namespace Fywolf\Billing\Provisioners;

use App\Enums\SuspendAction;
use App\Filament\Server\Pages\Console;
use App\Models\Allocation;
use App\Models\Objects\DeploymentObject;
use App\Models\Server;
use App\Services\Servers\ServerCreationService;
use App\Services\Servers\SuspensionService;
use Exception;
use Fywolf\Billing\Contracts\PackProvisionerContract;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Order;

class WingsProvisioner implements PackProvisionerContract
{
    public static function getSlug(): string
    {
        return 'wings';
    }

    public static function getLabel(): string
    {
        return 'Wings (Game Server)';
    }

    public function isProvisioned(Order $order): bool
    {
        return $order->server !== null;
    }

    public function provision(Order $order): void
    {
        if ($order->server) {
            return;
        }

        $pack  = $order->packPrice->pack;
        $price = $order->packPrice;

        $environment = [];
        foreach ($pack->egg->variables as $variable) {
            $environment[$variable->env_variable] = $variable->default_value;
        }

        if (!empty($price->environment_overrides)) {
            foreach ($price->environment_overrides as $override) {
                if (isset($override['variable'], $override['value'])) {
                    $environment[$override['variable']] = $override['value'];
                }
            }
        }

        $extraMemory = 0;
        $extraDisk   = 0;
        $extraCores  = 0;
        $extraAlloc  = 0;
        $extraDb     = 0;
        $extraBackup = 0;

        $order->load('orderExpansions.packExpansion.expansion');
        foreach ($order->orderExpansions as $orderExpansion) {
            $expansion    = $orderExpansion->packExpansion->expansion;
            $extraCores  += $expansion->cores_boost;
            $extraMemory += $expansion->memory_boost;
            $extraDisk   += $expansion->disk_boost;
            $extraAlloc  += $expansion->allocation_limit_boost;
            $extraDb     += $expansion->database_limit_boost;
            $extraBackup += $expansion->backup_limit_boost;
        }

        $data = [
            'name'                => $order->getLabel() . ' (' . $pack->getLabel() . ')',
            'owner_id'            => $order->customer->user->id,
            'egg_id'              => $pack->egg->id,
            'cpu'                 => ($price->cores + $extraCores) * 100,
            'memory'              => $price->memory + $extraMemory,
            'disk'                => $price->disk + $extraDisk,
            'swap'                => $price->swap,
            'io'                  => $price->io_weight,
            'environment'         => $environment,
            'skip_scripts'        => false,
            'start_on_completion' => true,
            'oom_killer'          => false,
            'database_limit'      => $price->database_limit + $extraDb,
            'allocation_limit'    => $price->allocation_limit + $extraAlloc,
            'backup_limit'        => $price->backup_limit + $extraBackup,
        ];

        if (!empty($pack->node_ids)) {
            $ports = [];
            if (!empty($pack->ports)) {
                foreach ($pack->ports as $portRange) {
                    if (str_contains((string) $portRange, '-')) {
                        [$start, $end] = explode('-', $portRange, 2);
                        $ports = array_merge($ports, range((int) $start, (int) $end));
                    } else {
                        $ports[] = (int) $portRange;
                    }
                }
            }

            $candidateNodeIds = Allocation::query()
                ->whereIn('node_id', $pack->node_ids)
                ->whereNull('server_id')
                ->when(!empty($ports), fn ($q) => $q->whereIn('port', $ports))
                ->distinct()
                ->pluck('node_id');

            if ($candidateNodeIds->isEmpty()) {
                throw new \RuntimeException(
                    'No available allocations on the configured nodes'
                    . (!empty($ports) ? ' matching ports: ' . implode(', ', $pack->ports) : '')
                    . '.'
                );
            }

            $usedCores = Server::whereIn('node_id', $candidateNodeIds)
                ->selectRaw('node_id, SUM(cpu) / 100.0 as used_cores')
                ->groupBy('node_id')
                ->pluck('used_cores', 'node_id');

            $bestNodeId = $candidateNodeIds
                ->sortBy(fn ($nodeId) => $usedCores->get($nodeId, 0))
                ->first();

            $allocationQuery = Allocation::query()
                ->where('node_id', $bestNodeId)
                ->whereNull('server_id');

            if (!empty($ports)) {
                $allocationQuery->whereIn('port', $ports);
            }

            $allocation = $allocationQuery->inRandomOrder()->first();

            if (!$allocation) {
                throw new \RuntimeException(
                    'No available allocations on the selected node'
                    . (!empty($ports) ? ' matching ports: ' . implode(', ', $pack->ports) : '')
                    . '.'
                );
            }

            $data['node_id']       = $allocation->node_id;
            $data['allocation_id'] = $allocation->id;

            $server = app(ServerCreationService::class)->handle($data);
        } else {
            $object = new DeploymentObject();
            $object->setDedicated(false);
            $object->setTags($pack->tags);
            $object->setPorts($pack->ports);

            $server = app(ServerCreationService::class)->handle($data, $object);
        }

        $order->update(['server_id' => $server->id]);

        AuditLog::record('server_created', [
            'server_id'   => $server->id,
            'server_name' => $server->name,
        ], $order);
    }

    public function suspend(Order $order): void
    {
        if (!$order->server) {
            return;
        }

        try {
            app(SuspensionService::class)->handle($order->server, SuspendAction::Suspend);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function unsuspend(Order $order): void
    {
        if (!$order->server) {
            return;
        }

        try {
            app(SuspensionService::class)->handle($order->server, SuspendAction::Unsuspend);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function terminate(Order $order): void
    {
        // Server deletion is managed by the panel directly
    }

    public function getManagementUrl(Order $order): ?string
    {
        if (!$order->server) {
            return null;
        }

        return Console::getUrl(panel: 'server', tenant: $order->server);
    }
}
