<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\HKI;
use App\Models\Publication;
use App\Models\Research;
use App\Models\Service;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware(['role:superadmin|admin']);
    }

    public function getTotalKinerja()
    {
        $total_researches = Research::count();
        $total_services = Service::count();
        $total_publications = Publication::count();
        $total_hki = HKI::count();
        $total_books = Book::count();
        $total = $total_researches + $total_services + $total_publications + $total_hki + $total_books;

        $total_kinerja = [
            'total_researches' => $total_researches,
            'total_services' => $total_services,
            'total_publications' => $total_publications,
            'total_hki' => $total_hki,
            'total_books' => $total_books,
            'total' => $total
        ];

        return $this->successResponse($total_kinerja, 'Total kinerja retrieved successfully.', 200);
    }
}
