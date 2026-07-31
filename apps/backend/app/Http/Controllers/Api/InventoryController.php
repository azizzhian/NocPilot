<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Pop;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function tree(Request $request): JsonResponse
    {
        $search = strtolower($request->string('search')->toString());

        $coreRouters = Router::where('name', 'like', '%CORE%')->get();
        if ($coreRouters->isEmpty()) {
            $coreRouters = Router::take(1)->get();
        }

        $tree = $coreRouters->map(function (Router $core) use ($search) {
            $coreNode = $this->assetNode('core', $core->id, $core->name, $core->status, $core->clients, 10000);

            $pops = Pop::with(['odcs.odps', 'olts'])->orderBy('name')->get();

            $coreNode['children'] = $pops
                ->filter(fn (Pop $pop) => $this->matches($search, $pop->name, $pop->code, $pop->area))
                ->map(function (Pop $pop) use ($search) {
                    $popStatus = $pop->status === 'active' ? 'online' : 'offline';
                    $popNode = $this->assetNode('pop', $pop->id, $pop->name, $popStatus, 0, $pop->capacity);

                    $odcChildren = $pop->odcs
                        ->filter(fn (Odc $odc) => $this->matches($search, $odc->name, $odc->code))
                        ->map(function (Odc $odc) use ($search) {
                            $odcNode = $this->assetNode('odc', $odc->id, $odc->name, $odc->status === 'active' ? 'online' : 'warning', 0, $odc->capacity);

                            $odcNode['children'] = $odc->odps
                                ->filter(fn (Odp $odp) => $this->matches($search, $odp->name, $odp->code))
                                ->map(function (Odp $odp) use ($search) {
                                    $usage = $odp->capacity > 0 ? (int) round(($odp->used_ports / $odp->capacity) * 100) : 0;
                                    $status = $odp->status === 'full' ? 'warning' : ($odp->status === 'active' ? 'online' : 'offline');
                                    $odpNode = $this->assetNode('odp', $odp->id, $odp->name, $status, $usage, $odp->capacity);

                                    $odpNode['children'] = Onu::with('customer:id,name,pppoe')
                                        ->where('odp_id', $odp->id)
                                        ->get()
                                        ->filter(fn (Onu $onu) => $this->matches($search, $onu->name, $onu->serial, $onu->customer?->name))
                                        ->map(function (Onu $onu) {
                                            $onuNode = $this->assetNode('onu', $onu->id, $onu->name, $onu->status === 'online' ? 'online' : 'offline', 0, 1);
                                            if ($onu->customer) {
                                                $onuNode['children'] = [[
                                                    ...$this->assetNode('customer', $onu->customer->id, $onu->customer->name, 'online', 0, 1),
                                                    'meta' => ['pppoe' => $onu->customer->pppoe],
                                                ]];
                                            }

                                            return $onuNode;
                                        })->values()->all();

                                    return $odpNode;
                                })->values()->all();

                            return $odcNode;
                        })->values()->all();

                    $oltChildren = $pop->olts
                        ->filter(fn (Olt $olt) => $this->matches($search, $olt->name, $olt->ip))
                        ->map(function (Olt $olt) {
                            $oltNode = $this->assetNode('olt', $olt->id, $olt->name, $olt->status, 0, $olt->capacity);
                            $oltNode['children'] = collect(range(1, min($olt->pon_ports, 4)))->map(fn ($i) =>
                                $this->assetNode('pon', $olt->id * 100 + $i, "PON 0/1/{$i}", 'online', 55, 64)
                            )->all();

                            return $oltNode;
                        })->values()->all();

                    $popNode['children'] = array_values(array_merge($odcChildren, $oltChildren));

                    return $popNode;
                })->values()->all();

            return $coreNode;
        })->values();

        return response()->json(['data' => $tree]);
    }

    public function flat(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();
        $rows = collect();

        Router::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->get(['id', 'name', 'status', 'clients'])
            ->each(fn ($r) => $rows->push([
                'id' => $r->id, 'name' => $r->name, 'type' => str_contains($r->name, 'CORE') ? 'Core' : 'Router',
                'status' => $r->status, 'capacity' => 10000, 'usage' => min(100, $r->clients > 0 ? (int) round($r->clients / 10) : 0), 'devices' => 1,
            ]));

        Pop::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->get()
            ->each(fn ($p) => $rows->push(['id' => $p->id, 'name' => $p->name, 'type' => 'POP', 'status' => $p->status === 'active' ? 'online' : 'offline', 'capacity' => $p->capacity, 'usage' => 65, 'devices' => $p->odcs()->count() + $p->olts()->count()]));

        Odc::with('pop')->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->get()
            ->each(fn ($o) => $rows->push(['id' => $o->id, 'name' => $o->name, 'type' => 'ODC', 'status' => $o->status === 'active' ? 'online' : 'offline', 'capacity' => $o->capacity, 'usage' => 78, 'devices' => $o->odps()->count()]));

        Odp::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->get()
            ->each(fn ($o) => $rows->push(['id' => $o->id, 'name' => $o->name, 'type' => 'ODP', 'status' => $o->status === 'full' ? 'warning' : 'online', 'capacity' => $o->capacity, 'usage' => $o->capacity > 0 ? (int) round(($o->used_ports / $o->capacity) * 100) : 0, 'devices' => $o->onus()->count()]));

        Olt::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->get()
            ->each(fn ($o) => $rows->push(['id' => $o->id, 'name' => $o->name, 'type' => 'OLT', 'status' => $o->status, 'capacity' => $o->capacity, 'usage' => 62, 'devices' => $o->onus()->count()]));

        Onu::when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))->get()
            ->each(fn ($o) => $rows->push(['id' => $o->id, 'name' => $o->name, 'type' => 'ONU', 'status' => $o->status === 'online' ? 'online' : 'offline', 'capacity' => 1, 'usage' => $o->status === 'online' ? 100 : 0, 'devices' => 1]));

        return response()->json(['data' => $rows->values()]);
    }

    protected function assetNode(string $type, int|string $id, string $name, string $status, int $usage, int $capacity): array
    {
        return [
            'id' => "{$type}-{$id}",
            'name' => $name,
            'type' => $type,
            'status' => $status,
            'usage' => $usage,
            'capacity' => $capacity,
            'devices' => 0,
            'children' => [],
        ];
    }

    protected function matches(string $search, ...$values): bool
    {
        if ($search === '') {
            return true;
        }

        foreach ($values as $value) {
            if ($value && str_contains(strtolower((string) $value), $search)) {
                return true;
            }
        }

        return false;
    }
}
