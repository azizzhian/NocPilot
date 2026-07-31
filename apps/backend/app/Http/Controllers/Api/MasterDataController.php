<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InternetPackage;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Pop;
use App\Models\Router;
use Illuminate\Http\JsonResponse;

class MasterDataController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'summary' => [
                ['key' => 'pops', 'label' => 'POP', 'count' => Pop::count()],
                ['key' => 'odcs', 'label' => 'ODC', 'count' => Odc::count()],
                ['key' => 'odps', 'label' => 'ODP', 'count' => Odp::count()],
                ['key' => 'olts', 'label' => 'OLT', 'count' => Olt::count()],
                ['key' => 'onus', 'label' => 'ONU', 'count' => Onu::count()],
                ['key' => 'routers', 'label' => 'Router', 'count' => Router::count()],
                ['key' => 'packages', 'label' => 'Paket Internet', 'count' => InternetPackage::count()],
                ['key' => 'customers', 'label' => 'Pelanggan', 'count' => Customer::count()],
            ],
            'links' => [
                ['label' => 'POP', 'to' => '/pop'],
                ['label' => 'ODC', 'to' => '/odc'],
                ['label' => 'ODP', 'to' => '/odp'],
                ['label' => 'OLT', 'to' => '/olt'],
                ['label' => 'ONU', 'to' => '/onu'],
                ['label' => 'Router', 'to' => '/router'],
                ['label' => 'Paket Internet', 'to' => '/paket'],
                ['label' => 'Pelanggan', 'to' => '/pelanggan'],
            ],
        ]);
    }
}
