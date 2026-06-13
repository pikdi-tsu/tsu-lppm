<?php

namespace App\Http\Controllers;

use App\Exports\BookExport;
use App\Imports\BookImport;
use App\Models\Author;
use App\Models\Book;
use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use App\Traits\FunctionalMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class BookController extends Controller
{
    use ApiResponse, FunctionalMethod;

    public function __construct()
    {
        $this->middleware('role:superadmin|admin')->except(['getBooksGroupedByCategory', 'getBooksChartData', 'getYearsOfBooksData']);
    }

    /**
     * @OA\Post(
     *     path="/api/books/import",
     *     summary="Import books data from Excel/CSV file",
     *     tags={"Books"},
     *     security={{ "bearer": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="file",
     *                     type="file",
     *                     description="Excel/CSV file containing books data"
     *                 ),
     *                 @OA\Property(
     *                     property="reset_table",
     *                     type="boolean",
     *                     description="Whether to reset the tables before import",
     *                     example=false
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Books imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books data imported successfully."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or import failure",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,xls,xlsx',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        try {
            $import = new BookImport();

            if ($request->boolean('reset_table')) {
                DB::table('books')->delete();
            }

            Excel::import($import, $request->file('file'));

            return $this->successResponse(null, 'Books data imported successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/books/export",
     *     tags={"Books"},
     *     summary="Export book data to Excel",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Excel file download",
     *         @OA\Header(
     *             header="Content-Type",
     *             @OA\Schema(type="string"),
     *             description="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *         ),
     *         @OA\Header(
     *             header="Content-Disposition",
     *             @OA\Schema(type="string"),
     *             description="attachment; filename=lppm-books.xlsx"
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error exporting data.")
     *         )
     *     )
     * )
     */
    public function export()
    {
        try {
            return Excel::download(new BookExport, 'lppm-books.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/books",
     *     tags={"Books"},
     *     summary="Create new book",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="tahun_terbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="isbn",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kategori",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tempat_terbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="penerbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="page",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="authors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=2
     *                 )
     *             ),
     *             example={"tahun_terbit": "2023", "isbn": "2342453", "kategori": "buku ajar", "title": "Book Title", "tempat_terbit": "Yogyakarta", "penerbit": "Airlangga", "page": "223", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Book Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="tahun_terbit", type="string", example="2023"),
     *                  @OA\Property(property="isbn", type="string", example="2342453"),
     *                  @OA\Property(property="kategori", type="string", example="buku ajar"),
     *                  @OA\Property(property="title", type="string", example="Book Title"),
     *                  @OA\Property(property="tempat_terbit", type="string", example="Yogyakarta"),
     *                  @OA\Property(property="penerbit", type="string", example="Airlangga"),
     *                  @OA\Property(property="page", type="string", example="223"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_terbit' => 'required|max:4',
            'isbn' => 'required|max:50',
            'kategori' => 'required|max:50',
            'title' => 'required|max:255|unique:books,title',
            'tempat_terbit' => 'required|max:100',
            'penerbit' => 'required|max:255',
            'page' => 'required|max:20',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->authors) {
            $authors = $request->authors;
            $creators = Author::whereIn('id', $authors)
                ->pluck('name')
                ->implode(', ');

            $request->merge(['creators' => $creators]);
        }

        $book = Book::create($request->all());
        $book->authors()->attach($request->authors);
        $book->save();

        $book->load('authors');

        return $this->successResponse($book, 'Book data created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/books/{id}",
     *     tags={"Books"},
     *     summary="Update an book by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the book",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *             @OA\Property(
     *                 property="tahun_terbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="isbn",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kategori",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tempat_terbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="penerbit",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="page",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="authors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=2
     *                 )
     *             ),
     *             example={"tahun_terbit": "2023", "isbn": "2342453", "kategori": "buku ajar", "title": "Book Title", "tempat_terbit": "Yogyakarta", "penerbit": "Airlangga", "page": "223", "authors": {"0": 2, "1": 4, "2": 8} }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Book Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book data updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="tahun_terbit", type="string", example="2023"),
     *                  @OA\Property(property="isbn", type="string", example="2342453"),
     *                  @OA\Property(property="kategori", type="string", example="buku ajar"),
     *                  @OA\Property(property="title", type="string", example="Book Title"),
     *                  @OA\Property(property="tempat_terbit", type="string", example="Yogyakarta"),
     *                  @OA\Property(property="penerbit", type="string", example="Airlangga"),
     *                  @OA\Property(property="page", type="string", example="223"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tahun_terbit' => 'required|max:4',
            'isbn' => 'required|max:50',
            'kategori' => 'required|max:50',
            'title' => 'required|max:255|unique:books,title,' . $id,
            'tempat_terbit' => 'required|max:100',
            'penerbit' => 'required|max:255',
            'page' => 'required|max:20',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $book = Book::find($id);
        if (!$book) {
            return $this->errorResponse('Book not found.', 404);
        }

        if ($request->authors) {
            $authors = $request->authors;
            $creators = Author::whereIn('id', $authors)
                ->pluck('name')
                ->implode(', ');

            $request->merge(['creators' => $creators]);
        }

        $book->update($request->all());
        $book->authors()->sync($request->authors);
        $book->save();

        $book->load('authors');

        return $this->successResponse($book, 'Book data successfully updated.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/books",
     *     summary="Get paginated list of books",
     *     description="Returns paginated books with optional search functionality",
     *     security={{"bearer_token": {}}},
     *     tags={"Books"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for filtering data by title or creators name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                        @OA\Property(property="id", type="integer", example=1),
     *                        @OA\Property(property="tahun_terbit", type="string", example="2023"),
     *                        @OA\Property(property="isbn", type="string", example="2342453"),
     *                        @OA\Property(property="kategori", type="string", example="buku ajar"),
     *                        @OA\Property(property="title", type="string", example="Book Title"),
     *                        @OA\Property(property="tempat_terbit", type="string", example="Yogyakarta"),
     *                        @OA\Property(property="penerbit", type="string", example="Airlangga"),
     *                        @OA\Property(property="page", type="string", example="223"),
     *                         @OA\Property(
     *                             property="authors",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="sinta_id", type="string", example="66889756"),
     *                                 @OA\Property(property="nidn", type="string", example="2342453"),
     *                                 @OA\Property(property="name", type="string", example="Yustina"),
     *                                 @OA\Property(property="affiliation", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                                 @OA\Property(property="study_program_id", type="integer", example="2"),
     *                                 @OA\Property(property="last_education", type="string", example="S2"),
     *                                 @OA\Property(property="functional_position", type="string", example="Asisten Ahli"),
     *                                 @OA\Property(property="title_prefix", type="string", example=null),
     *                                 @OA\Property(property="title_suffix", type="string", example="S.Kom, M.Kom"),
     *                             ),
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                      property="meta",
     *                      type="object",
     *                          @OA\Property(property="total", type="integer", example=100),   
     *                          @OA\Property(property="per_page", type="integer", example=10),
     *                          @OA\Property(property="current_page", type="integer", example=1),
     *                          @OA\Property(property="last_page", type="integer", example=10),
     *                  )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getBooks()
    {
        $query = Book::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->whereAny(['title', 'creators'], 'like', "%$search_term%");
        }

        $books = $query->with('authors')->latest()->paginate(10);

        return $this->paginatedResponse($books, 'Books data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/books/{id}",
     *     summary="Get book by ID",
     *     description="Returns a specific book by its ID with related authors",
     *     security={{"bearer_token": {}}},
     *     tags={"Books"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of book to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tahun_terbit", type="string", example="2023"),
     *                 @OA\Property(property="isbn", type="string", example="2342453"),
     *                 @OA\Property(property="kategori", type="string", example="buku ajar"),
     *                 @OA\Property(property="title", type="string", example="Book Title"),
     *                 @OA\Property(property="tempat_terbit", type="string", example="Yogyakarta"),
     *                 @OA\Property(property="penerbit", type="string", example="Airlangga"),
     *                 @OA\Property(property="page", type="string", example="223"),
     *                 @OA\Property(
     *                     property="authors",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                        @OA\Property(property="id", type="integer", example=1),
     *                        @OA\Property(property="sinta_id", type="string", example="34514545"),
     *                        @OA\Property(property="nidn", type="string", example="2342453"),
     *                        @OA\Property(property="name", type="string", example="Yustina"),
     *                        @OA\Property(property="affiliation", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                        @OA\Property(property="study_program_id", type="integer", example=3),
     *                        @OA\Property(property="last_education", type="string", example="S1"),
     *                        @OA\Property(property="functional_position", type="string", example="Lektor"),
     *                        @OA\Property(property="title_prefix", type="string", example="Prof."),
     *                        @OA\Property(property="title_suffix", type="string", example="S.T."),
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Data not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Data data not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getBookByID($id)
    {
        $book = Book::with('authors')->find($id);
        if (!$book) {
            return $this->errorResponse('Book not found.', 404);
        }

        return $this->successResponse($book, 'Book data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/books/grouped-by-category",
     *     summary="Get books grouped by category",
     *     description="Retrieves books data grouped by `kategori`, with an optional filter by `study_program_id`.",
     *     tags={"Books"},
     *     @OA\Parameter(
     *         name="study_program_id",
     *         in="query",
     *         description="Filter books by study program ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of books data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="hak cipta", type="object",
     *                     @OA\Property(property="count", type="integer", example=2),
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getBooksGroupedByCategory()
    {
        $query = Book::with('authors');

        if (request()->has('study_program_id')) {
            $study_program_id = request()->input('study_program_id');
            $query->whereHas('authors', function ($q) use ($study_program_id) {
                $q->where('study_program_id', $study_program_id);
            });
        }

        $books = $query->get();
        $grouped_data = $books->groupBy('kategori')->map(function ($group) {
            return [
                'count' => $group->count()
            ];
        });

        return $this->successResponse($grouped_data, 'Books data retrieved successfully', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/books/chart-data",
     *     summary="Get books statistics chart data",
     *     description="Retrieves books statistics grouped by study programs for chart visualization",
     *     tags={"Books"},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filter statistics by year",
     *         required=false,
     *         @OA\Schema(type="string", example="2024")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Books chart data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="labels",
     *                     type="array",
     *                     @OA\Items(type="string", example="S1-Informatika")
     *                 ),
     *                 @OA\Property(
     *                     property="datasets",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(type="integer", example=10)
     *                     ),
     *                     @OA\Property(
     *                         property="background_color",
     *                         type="array",
     *                         @OA\Items(type="string", example="#FF5733")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="study_programs",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="name", type="string", example="S1-Informatika"),
     *                         @OA\Property(property="total", type="integer", example=10),
     *                         @OA\Property(property="percentage", type="number", format="float", example=25.5)
     *                     )
     *                 ),
     *                 @OA\Property(property="total_books", type="integer", example=40)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function getBooksChartData()
    {
        // Base query starting with study programs
        $books_by_program = StudyProgram::select(
            'study_programs.name as study_program',
            DB::raw('COALESCE(COUNT(DISTINCT books.id), 0) as total_books')
        )
            ->leftJoin('authors', 'study_programs.id', '=', 'authors.study_program_id')
            ->leftJoin('author_book', 'authors.id', '=', 'author_book.author_id')
            ->leftJoin('books', 'author_book.book_id', '=', 'books.id');

        // Apply year filter if provided
        if (request()->has('year')) {
            $year = request()->input('year');
            $books_by_program->where('books.tahun_terbit', $year);
        }

        // Complete the query
        $books_by_program = $books_by_program
            ->groupBy('study_programs.id', 'study_programs.name')
            ->orderBy('study_programs.name')
            ->get();

        // Calculate total books
        $total_books = $books_by_program->sum('total_books');

        // Prepare chart data
        $chart_data = [
            'labels' => $books_by_program->pluck('study_program')->toArray(),
            'datasets' => [
                'data' => $books_by_program->pluck('total_books')->toArray(),
                'background_color' => $this->generateColors(count($books_by_program))
            ],
            'study_programs' => $books_by_program->map(function ($item) use ($total_books) {
                return [
                    'name' => $item->study_program,
                    'total' => $item->total_books,
                    'percentage' => $total_books > 0 ? round(($item->total_books / $total_books) * 100, 2) : 0,
                ];
            }),
            'total_books' => $total_books
        ];

        return $this->successResponse($chart_data, 'Books chart data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/books/years",
     *     summary="Get years of books data",
     *     description="Retrieves a list of years in which books were conducted",
     *     tags={"Books"},
     *     @OA\Response(
     *         response=200,
     *         description="Years data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="string"), example={"2020", "2021", "2022", "2023"}),
     *             @OA\Property(property="message", type="string", example="Years data retrieved successfully.")
     *         )
     *     )
     * )
     */
    public function getYearsOfBooksData()
    {
        $years = Book::groupBy('tahun_terbit')->pluck('tahun_terbit');

        return $this->successResponse($years, 'Years data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/books/{id}",
     *     summary="Delete a book",
     *     description="Deletes a book record by ID",
     *     security={{"bearer_token": {}}},
     *     tags={"Books"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of book to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book data deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Book not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Book data not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        $book = Book::find($id);
        if (!$book) {
            return $this->errorResponse('Book not found.', 404);
        }

        $book->delete();

        return $this->successResponse(null, 'Book deleted successfully.', 200);
    }
}
