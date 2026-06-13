<?php

namespace App\Http\Controllers;

use App\Exports\PublicationExport;
use App\Imports\GoogleImport;
use App\Imports\ScopusImport;
use App\Models\Author;
use App\Models\Publication;
use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use App\Traits\FunctionalMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PublicationController extends Controller
{
    use ApiResponse, FunctionalMethod;


    public function __construct()
    {
        $this->middleware('role:superadmin|admin')->except(['getDataGroupedByAccreditationAndQuartile', 'getChartsData', 'getYearsOfPublicationsData']);
    }

    /**
     * @OA\Post(
     *     path="/api/publications/import",
     *     summary="Import publications data from Excel/CSV file",
     *     tags={"Publications"},
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
     *                     description="Excel/CSV file containing publications data"
     *                 ),
     *                 @OA\Property(
     *                     property="category",
     *                     type="string",
     *                     enum={"google", "scopus"},
     *                     description="Import category type"
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
     *         description="Publications imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Publication data imported successfully."),
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
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
            'category' => 'required|string|in:google,scopus',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        try {
            $import = null;

            if ($request->boolean('reset_table')) {
                DB::table('author_publication')->delete();
                DB::table('publications')->delete();
            }

            if ($request->category == 'scopus') {
                $import = new ScopusImport();
            } elseif ($request->category == 'google') {
                $import = new GoogleImport();
            }

            Excel::import($import, $request->file('file'));

            return $this->successResponse(null, 'Publication data imported successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/publications/export",
     *     tags={"Publications"},
     *     summary="Export publication data to Excel",
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
     *             description="attachment; filename=lppm-publications.xlsx"
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
            return Excel::download(new PublicationExport, 'lppm-publications.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/publications",
     *     tags={"Publications"},
     *     summary="Create new publication",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 enum={"google", "scopus"},
     *             ),
     *             @OA\Property(
     *                 property="accreditation",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="identifier",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="quartile",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="journal",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="publication_name",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="year",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="citation",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="authors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=2
     *                 )
     *             ),
     *             example={"category": "google", "identifier": null, "quartile": null, "accreditation": "S3", "title": "Publikasi 1", "journal": "Journal of information", "publication_name": null, "year": "2024", "citation": "1", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Publication Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Publication data created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="category", type="string", example="google"),
     *                  @OA\Property(property="accreditation", type="string", example="S3"),
     *                  @OA\Property(property="title", type="string", example="Publikasi 1"),
     *                  @OA\Property(property="journal", type="string", example="Journal of information"),
     *                  @OA\Property(property="year", type="string", example="2024"),
     *                  @OA\Property(property="citation", type="string", example="1"),
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
            'category' => 'required|in:google,scopus',
            'accreditation' => 'required_if:category,google|string|max:50',
            'identifier' => 'required_if:category,scopus|string|max:50',
            'quartile' => 'required_if:category,scopus|string|max:50',
            'title' => 'required|string|max:255',
            'journal' => 'required_if:category,google|string|max:255',
            'publication_name' => 'required_if:category,scopus|string|max:255',
            'year' => 'required|max:4',
            'citation' => 'required|string|max:10',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id',
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

        $data = Publication::create($request->all());
        $data->authors()->attach($request->authors);
        $data->save();

        $data->load('authors');

        return $this->successResponse($data, 'Publication created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/publications/{id}",
     *     tags={"Publications"},
     *     summary="Update an publication by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the publication",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *             @OA\Property(
     *                 property="category",
     *                 type="string",
     *                 enum={"google", "scopus"},
     *             ),
     *             @OA\Property(
     *                 property="accreditation",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="identifier",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="quartile",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="journal",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="publication_name",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="year",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="citation",
     *                 type="string",
     *             ),
     *             @OA\Property(
     *                 property="authors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=2
     *                 )
     *             ),
     *             example={"category": "google", "identifier": null, "quartile": null, "accreditation": "S3", "title": "Publikasi 1", "journal": "Journal of information", "publication_name": null, "year": "2024", "citation": "1", "authors": {"0": 2, "1": 4, "2": 8} }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Publication Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Publication data updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="category", type="string", example="google"),
     *                  @OA\Property(property="accreditation", type="string", example="S3"),
     *                  @OA\Property(property="title", type="string", example="Publikasi 1"),
     *                  @OA\Property(property="journal", type="string", example="Journal of information"),
     *                  @OA\Property(property="year", type="string", example="2024"),
     *                  @OA\Property(property="citation", type="string", example="1"),
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
            'category' => 'required|in:google,scopus',
            'accreditation' => 'required_if:category,google|string|max:50',
            'identifier' => 'required_if:category,scopus|string|max:50',
            'quartile' => 'required_if:category,scopus|string|max:50',
            'title' => 'required|string|max:255|unique:publications,title,' . $id,
            'journal' => 'required_if:category,google|string|max:255',
            'publication_name' => 'required_if:category,scopus|string|max:255',
            'year' => 'required|max:4',
            'citation' => 'required|string|max:10',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $data = Publication::find($id);
        if (!$data) {
            return $this->errorResponse('Publication not found.', 404);
        }

        $authors = $request->authors;

        $creators = Author::whereIn('id', $authors)
            ->pluck('name')
            ->implode(', ');

        $request->merge(['creators' => $creators]);

        if ($request->category == 'google') {
            $data->update([
                'category' => $request->category,
                'accreditation' => $request->accreditation,
                'identifier' => null,
                'quartile' => null,
                'title' => $request->title,
                'journal' => $request->journal,
                'publication_name' => null,
                'year' => $request->year,
                'citation' => $request->citation,
                'creators' => $creators,
            ]);
        } else {
            $data->update([
                'category' => $request->category,
                'accreditation' => null,
                'identifier' => $request->identifier,
                'quartile' => $request->quartile,
                'title' => $request->title,
                'journal' => null,
                'publication_name' => $request->publication_name,
                'year' => $request->year,
                'citation' => $request->citation,
                'creators' => $creators,
            ]);
        }

        $data->update($request->all());
        $data->authors()->sync($request->authors);
        $data->save();

        $data->load('authors');

        return $this->successResponse($data, 'Publication updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/publications",
     *     summary="Get paginated list of publications",
     *     description="Returns paginated publications with optional search functionality",
     *     security={{"bearer_token": {}}},
     *     tags={"Publications"},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Search term for filtering data by google or scopus",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for filtering data by title, journal or creators",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="publication data retrieved successfully."),
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
     *                        @OA\Property(property="accreditation", type="string", example="S3"),
     *                        @OA\Property(property="identifier", type="string", example="34576"),
     *                        @OA\Property(property="quartile", type="string", example="Q1"),
     *                        @OA\Property(property="title", type="string", example="Publikasi 1"),
     *                        @OA\Property(property="journal", type="string", example="Journal of information"),
     *                        @OA\Property(property="publication_name", type="string", example="Journal of technology"),
     *                        @OA\Property(property="year", type="string", example="2024"),
     *                        @OA\Property(property="citation", type="string", example="1"),
     *                        @OA\Property(property="category", type="string", example="google"),
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
    public function getPublications()
    {
        $query = Publication::query();

        // Get the category from the request
        $category = request()->input('category', '');
        $search_term = request()->input('q');

        /// Apply category filter if provided
        if ($category) {
            $query->where('category', $category);
        }

        // Apply search term filter if provided
        if ($search_term) {
            // Define searchable fields based on the category
            $searchableFields = $this->getSearchableFields($category);
            $query->whereAny($searchableFields, 'like', "%$search_term%");
        }

        // Get selectable fields based on the category
        $selectable_fields = $this->getSelectableFields($category);
        $query->select($selectable_fields);

        $data = $query->with('authors')->latest()->paginate(10);

        return $this->paginatedResponse($data, 'Publications retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/publications/{id}",
     *     summary="Get publication by ID",
     *     description="Returns a specific publication by its ID with related authors",
     *     security={{"bearer_token": {}}},
     *     tags={"Publications"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of publication to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="publication data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="accreditation", type="string", example="S3"),
     *                 @OA\Property(property="identifier", type="string", example="34576"),
     *                 @OA\Property(property="quartile", type="string", example="Q1"),
     *                 @OA\Property(property="title", type="string", example="Publikasi 1"),
     *                 @OA\Property(property="journal", type="string", example="Journal of information"),
     *                 @OA\Property(property="publication_name", type="string", example="Journal of technology"),
     *                 @OA\Property(property="year", type="string", example="2024"),
     *                 @OA\Property(property="citation", type="string", example="1"),
     *                 @OA\Property(property="category", type="string", example="scopus"),
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
    public function getPublicationByID($id)
    {
        $data = Publication::find($id);
        if (!$data) {
            return $this->errorResponse('Publication not found.', 404);
        }

        // Get selectable fields based on the category
        $selectable_fields = $this->getSelectableFields($data->category);
        $publication = Publication::select($selectable_fields)->with('authors')->find($id);

        return $this->successResponse($publication, 'Publication retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/publications/grouped",
     *     summary="Get publications grouped by scheme",
     *     description="Retrieves publications data grouped by `accreditation`, with an optional filter by `study_program_id`.",
     *     tags={"Publications"},
     *     @OA\Parameter(
     *         name="study_program_id",
     *         in="query",
     *         description="Filter publications by study program ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of publications data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Publications data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="S3", type="object",
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
    public function getDataGroupedByAccreditationAndQuartile()
    {
        $query = Publication::with('authors');

        if (request()->has('study_program_id')) {
            $study_program_id = request()->input('study_program_id');
            $query->whereHas('authors', function ($q) use ($study_program_id) {
                $q->where('study_program_id', $study_program_id);
            });
        }

        $data = $query->get();

        $grouped_data = $data
            ->filter(function ($item) {
                // Ensure at least one key is filled and not empty
                return !empty($item['accreditation']) || !empty($item['quartile']);
            })
            ->groupBy(function ($item) {
                // Group by the filled key: 'accreditation' or 'quartile'
                return !empty($item['accreditation']) ? $item['accreditation'] : $item['quartile'];
            })
            ->map(function ($group) {
                return [
                    'count' => $group->count(), // Count the items in each group
                ];
            });



        return $this->successResponse($grouped_data, 'Publications grouped by accreditation and quartile retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/publications/chart-data",
     *     summary="Get Publications statistics chart data",
     *     description="Retrieves Publications statistics grouped by study programs for chart visualization",
     *     tags={"Publications"},
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
     *             @OA\Property(property="message", type="string", example="Publications chart data retrieved successfully."),
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
     *                 @OA\Property(property="total_data", type="integer", example=40)
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
    public function getChartsData()
    {
        // Base query starting with study programs
        $publications_by_program = StudyProgram::select(
            'study_programs.name as study_program',
            DB::raw('COALESCE(COUNT(DISTINCT publications.id), 0) as total_publications')
        )
            ->leftJoin('authors', 'study_programs.id', '=', 'authors.study_program_id')
            ->leftJoin('author_publication', 'authors.id', '=', 'author_publication.author_id')
            ->leftJoin('publications', 'author_publication.publication_id', '=', 'publications.id');

        // Apply year filter if provided
        if (request()->has('year')) {
            $year = request()->input('year');
            $publications_by_program->where('publications.year', $year);
        }

        // Complete the query
        $publications_by_program = $publications_by_program
            ->groupBy('study_programs.id', 'study_programs.name')
            ->orderBy('study_programs.name')
            ->get();

        // Calculate total publications
        $total_publications = $publications_by_program->sum('total_publications');

        // Prepare chart data
        $chart_data = [
            'labels' => $publications_by_program->pluck('study_program')->toArray(),
            'datasets' => [
                'data' => $publications_by_program->pluck('total_publications')->toArray(),
                'background_color' => $this->generateColors(count($publications_by_program))
            ],
            'study_programs' => $publications_by_program->map(function ($item) use ($total_publications) {
                return [
                    'name' => $item->study_program,
                    'total' => $item->total_publications,
                    'percentage' => $total_publications > 0 ? round(($item->total_publications / $total_publications) * 100, 2) : 0,
                ];
            }),
            'total_publications' => $total_publications
        ];

        return $this->successResponse($chart_data, 'Chart data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/publications/years",
     *     summary="Get years of publications data",
     *     description="Retrieves a list of years in which publications were conducted",
     *     tags={"Publications"},
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
    public function getYearsOfPublicationsData()
    {
        $years = Publication::groupBy('year')->pluck('year');

        return $this->successResponse($years, 'Years data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/publications/{id}",
     *     summary="Delete a publication",
     *     description="Deletes a publication record by ID",
     *     security={{"bearer_token": {}}},
     *     tags={"Publications"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of publication to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Publication deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Publication data deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Publication not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Publication data not found.")
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
        $data = Publication::find($id);
        if (!$data) {
            return $this->errorResponse('Publication not found.', 404);
        }

        $data->delete();

        return $this->successResponse(null, 'Publication deleted successfully.', 200);
    }
}
