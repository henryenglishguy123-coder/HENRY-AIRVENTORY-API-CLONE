<?php

namespace App\Http\Controllers\Api\V1\Factory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreFactorySalesRoutingRequest;
use App\Models\Factory\FactorySalesRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FactorySalesRoutingApiController extends Controller
{
    public function index(Request $request)
    {
        $draw = (int) $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);

        // Group by both factory_id and priority
        $routingQuery = FactorySalesRouting::query()
            ->select('factory_id', 'priority')
            ->groupBy('factory_id', 'priority');

        // Get total unique (factory, priority) combinations
        $recordsTotal = DB::table('factory_sales_routing')
            ->distinct()
            ->count(DB::raw('CONCAT(factory_id, "-", priority)'));

        // For filtered count, we clone the query
        // Note: if you add search filters later, apply them to $routingQuery before this
        $recordsFiltered = DB::table('factory_sales_routing')
            ->select('factory_id', 'priority')
            ->groupBy('factory_id', 'priority')
            ->get()
            ->count();

        // Get the paginated unique keys
        $keys = $routingQuery
            ->orderBy('factory_id')
            ->orderBy('priority')
            ->skip($start)
            ->take($length)
            ->get();

        // Load details for these keys
        $data = $keys->map(function ($key) {
            $routes = FactorySalesRouting::with(['factory.business', 'country'])
                ->where('factory_id', $key->factory_id)
                ->where('priority', $key->priority)
                ->get();

            $first = $routes->first();
            $factoryName = null;
            if ($first) {
                $factoryName = trim(
                    ($first->factory?->first_name.' '.$first->factory?->last_name)
                ).' ('.optional($first->factory?->business)->company_name.')';
            }

            return [
                'factory_id' => $key->factory_id,
                'factory' => $factoryName,
                'priority' => $key->priority,
                'countries' => $routes->pluck('country.name')->values(),
                'country_ids' => $routes->pluck('country_id')->values(),
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function store(StoreFactorySalesRoutingRequest $request)
    {
        return $this->syncRouting(
            $request->factory_id,
            $request->country_ids,
            $request->priority
        );
    }

    public function update(StoreFactorySalesRoutingRequest $request, $factoryId)
    {
        return $this->syncRouting(
            $factoryId,
            $request->country_ids,
            $request->priority
        );
    }

    public function destroy($factoryId)
    {
        FactorySalesRouting::where('factory_id', $factoryId)->delete();

        return response()->json([
            'success' => true,
            'message' => __('Routing deleted successfully'),
        ]);
    }

    private function syncRouting($factoryId, array $countryIds, int $priority)
    {
        // 1. Delete routings for this factory AND this priority that are NOT in the new list.
        // This ensures if a user removes a country from a specific priority group, it gets deleted.
        // It does NOT touch other priorities.
        FactorySalesRouting::where('factory_id', $factoryId)
            ->where('priority', $priority)
            ->whereNotIn('country_id', $countryIds)
            ->delete();

        // 2. Update or Create the routings in the list
        foreach ($countryIds as $countryId) {
            FactorySalesRouting::updateOrCreate(
                [
                    'factory_id' => $factoryId,
                    'country_id' => $countryId,
                ],
                ['priority' => $priority]
            );
        }

        return response()->json([
            'success' => true,
            'message' => __('Routing saved successfully'),
        ]);
    }
}
