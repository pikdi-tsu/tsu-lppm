<?php

namespace App\Http\Controllers;

use App\Exports\HKIExport;
use App\Imports\HKIImport;
use App\Models\HKI;
use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use App\Traits\FunctionalMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class HKIController extends Controller
{
    use ApiResponse, FunctionalMethod;

    public function __construct()
    {
        $this->middleware('role:superadmin|admin')->except(['getHKIDataGroupedByCategory', 'getHKIChartData']);
    }

    /**
     * @OA\Post(
     *     path="/api/hki/import",
     *     summary="Import hki data from Excel/CSV file",
     *     tags={"HKI"},
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
     *                     description="Excel/CSV file containing hki data"
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
     *         description="HKI imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data imported successfully."),
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
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        try {
            $file = $request->file('file');
            $import = new HKIImport();

            if ($request->boolean('reset_table')) {
                DB::table('hkis')->delete();
                DB::table('author_hki')->delete();
            }

            Excel::import($import, $file);

            return $this->successResponse(null, 'Data imported successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/hki/export",
     *     tags={"HKI"},
     *     summary="Export HKI data to Excel",
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
     *             description="attachment; filename=lppm-hki.xlsx"
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
            return Excel::download(new HKIExport, 'lppm-hki.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/hki",
     *     tags={"HKI"},
     *     summary="Create new hki",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="tahun_permohonan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_permohonan",
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
     *                 property="pemegang_paten",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="inventor",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_publikasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tanggal_publikasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="filing_date",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="reception_date",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_registrasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tanggal_registrasi",
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
     *             example={"tahun_permohonan": "2023", "nomor_permohonan": "2342453", "kategori": "hak cipta", "title": "SISTEM INFORMASI", "pemegang_paten": "Budi, Joko", "inventor": "Iwan Ady", "status": "Graded", "nomor_publikasi": "00034523", "tanggal_publikasi": "2023-09-10", "filing_date": "2023-09-11", "reception_date": "2023-09-13", "nomor_registrasi": "003458359", "tanggal_registrasi": "2023-09-18", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="HKI Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="tahun_permohonan", type="string", example="2023"),
     *                  @OA\Property(property="nomor_permohonan", type="string", example="2342453"),
     *                  @OA\Property(property="kategori", type="string", example="hak cipta"),
     *                  @OA\Property(property="title", type="string", example="SISTEM INFORMASI"),
     *                  @OA\Property(property="pemegang_paten", type="string", example="Budi, Joko"),
     *                  @OA\Property(property="inventor", type="string", example="Iwan Ady"),
     *                  @OA\Property(property="status", type="string", example="Graded"),
     *                  @OA\Property(property="nomor_publikasi", type="string", example="00034523"),
     *                  @OA\Property(property="tanggal_publikasi", type="string", example="2023-09-10"),
     *                  @OA\Property(property="filing_date", type="string", example="2023-09-11"),
     *                  @OA\Property(property="reception_date", type="string", example="2023-09-13"),
     *                  @OA\Property(property="nomor_registrasi", type="string", example="003458359"),
     *                  @OA\Property(property="tanggal_registrasi", type="string", example="2023-09-18"),
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
            'tahun_permohonan' => 'string|nullable|max:4',
            'nomor_permohonan' => 'string|required|max:50',
            'kategori' => 'string|nullable|max:50',
            'title' => 'string|required|max:255|unique:hkis,title',
            'pemegang_paten' => 'string|nullable|max:255',
            'inventor' => 'string|nullable|max:255',
            'status' => 'string|nullable|max:50',
            'nomor_publikasi' => 'string|required|max:50',
            'tanggal_publikasi' => 'required|date',
            'filing_date' => 'required|date',
            'reception_date' => 'required|date',
            'nomor_registrasi' => 'string|required|max:50',
            'tanggal_registrasi' => 'required|date',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 422);
        }

        $data = HKI::create($request->all());
        $data->authors()->attach($request->authors);
        $data->save();

        $data->load('authors');

        return $this->successResponse($data, 'Data created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/hki/{id}",
     *     tags={"HKI"},
     *     summary="Update an hki by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the hki",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *             @OA\Property(
     *                 property="tahun_permohonan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_permohonan",
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
     *                 property="pemegang_paten",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="inventor",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_publikasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tanggal_publikasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="filing_date",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="reception_date",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nomor_registrasi",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="tanggal_registrasi",
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
     *             example={"tahun_permohonan": "2023", "nomor_permohonan": "2342453", "kategori": "hak cipta", "title": "SISTEM INFORMASI", "pemegang_paten": "Budi, Joko", "inventor": "Iwan Ady", "status": "Graded", "nomor_publikasi": "00034523", "tanggal_publikasi": "2023-09-10", "filing_date": "2023-09-11", "reception_date": "2023-09-13", "nomor_registrasi": "003458359", "tanggal_registrasi": "2023-09-18", "authors": {"0": 2, "1": 4, "2": 8} }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="HKI Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="tahun_permohonan", type="string", example="2023"),
     *                  @OA\Property(property="nomor_permohonan", type="string", example="2342453"),
     *                  @OA\Property(property="kategori", type="string", example="hak cipta"),
     *                  @OA\Property(property="title", type="string", example="SISTEM INFORMASI"),
     *                  @OA\Property(property="pemegang_paten", type="string", example="Budi, Joko"),
     *                  @OA\Property(property="inventor", type="string", example="Iwan Ady"),
     *                  @OA\Property(property="status", type="string", example="Graded"),
     *                  @OA\Property(property="nomor_publikasi", type="string", example="00034523"),
     *                  @OA\Property(property="tanggal_publikasi", type="string", example="2023-09-10"),
     *                  @OA\Property(property="filing_date", type="string", example="2023-09-11"),
     *                  @OA\Property(property="reception_date", type="string", example="2023-09-13"),
     *                  @OA\Property(property="nomor_registrasi", type="string", example="003458359"),
     *                  @OA\Property(property="tanggal_registrasi", type="string", example="2023-09-18"),
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
            'tahun_permohonan' => 'string|nullable|max:4',
            'nomor_permohonan' => 'string|required|max:50',
            'kategori' => 'string|nullable|max:50',
            'title' => 'string|required|max:255|unique:hkis,title,' . $id,
            'pemegang_paten' => 'string|nullable|max:255',
            'inventor' => 'string|nullable|max:255',
            'status' => 'string|nullable|max:50',
            'nomor_publikasi' => 'string|required|max:50',
            'tanggal_publikasi' => 'required|date',
            'filing_date' => 'required|date',
            'reception_date' => 'required|date',
            'nomor_registrasi' => 'string|required|max:50',
            'tanggal_registrasi' => 'required|date',
            'authors' => 'nullable|array',
            'authors.*' => 'nullable|exists:authors,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 422);
        }

        $data = HKI::find($id);
        if (!$data) {
            return $this->errorResponse('Data not found.', 404);
        }

        $data->update($request->all());
        $data->authors()->sync($request->authors);
        $data->save();

        $data->load('authors');

        return $this->successResponse($data, 'Data updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/hki",
     *     summary="Get paginated list of hki",
     *     description="Returns paginated hki with optional search functionality",
     *     security={{"bearer_token": {}}},
     *     tags={"HKI"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for filtering data by nomor permohonan, journal or title",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data retrieved successfully."),
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
     *                        @OA\Property(property="tahun_permohonan", type="string", example="2023"),
     *                        @OA\Property(property="nomor_permohonan", type="string", example="2342453"),
     *                        @OA\Property(property="kategori", type="string", example="hak cipta"),
     *                        @OA\Property(property="title", type="string", example="SISTEM INFORMASI"),
     *                        @OA\Property(property="pemegang_paten", type="string", example="Budi, Joko"),
     *                        @OA\Property(property="inventor", type="string", example="Iwan Ady"),
     *                        @OA\Property(property="status", type="string", example="Graded"),
     *                        @OA\Property(property="nomor_publikasi", type="string", example="00034523"),
     *                        @OA\Property(property="tanggal_publikasi", type="string", example="2023-09-10"),
     *                        @OA\Property(property="filing_date", type="string", example="2023-09-11"),
     *                        @OA\Property(property="reception_date", type="string", example="2023-09-13"),
     *                        @OA\Property(property="nomor_registrasi", type="string", example="003458359"),
     *                        @OA\Property(property="tanggal_registrasi", type="string", example="2023-09-18"),
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
    public function getHKIData()
    {
        $query = HKI::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->whereAny(['nomor_permohonan', 'title'], 'like', "%$search_term%");
        }

        $data = $query->with('authors')->latest()->paginate(10);

        return $this->successResponse($data, 'Data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/hki/{id}",
     *     summary="Get hki by ID",
     *     description="Returns a specific hki by its ID with related authors",
     *     security={{"bearer_token": {}}},
     *     tags={"HKI"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of hki to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="tahun_permohonan", type="string", example="2023"),
     *                  @OA\Property(property="nomor_permohonan", type="string", example="2342453"),
     *                  @OA\Property(property="kategori", type="string", example="hak cipta"),
     *                  @OA\Property(property="title", type="string", example="SISTEM INFORMASI"),
     *                  @OA\Property(property="pemegang_paten", type="string", example="Budi, Joko"),
     *                  @OA\Property(property="inventor", type="string", example="Iwan Ady"),
     *                  @OA\Property(property="status", type="string", example="Graded"),
     *                  @OA\Property(property="nomor_publikasi", type="string", example="00034523"),
     *                  @OA\Property(property="tanggal_publikasi", type="string", example="2023-09-10"),
     *                  @OA\Property(property="filing_date", type="string", example="2023-09-11"),
     *                  @OA\Property(property="reception_date", type="string", example="2023-09-13"),
     *                  @OA\Property(property="nomor_registrasi", type="string", example="003458359"),
     *                  @OA\Property(property="tanggal_registrasi", type="string", example="2023-09-18"),
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
    public function getHKIDataById($id)
    {
        $data = HKI::with('authors')->find($id);
        if (!$data) {
            return $this->errorResponse('Data not found.', 404);
        }

        return $this->successResponse($data, 'Data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/hki/grouped-by-category",
     *     summary="Get hki grouped by category",
     *     description="Retrieves hki data grouped by `kategori`, with an optional filter by `study_program_id`.",
     *     tags={"HKI"},
     *     @OA\Parameter(
     *         name="study_program_id",
     *         in="query",
     *         description="Filter hki by study program ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of hki data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data retrieved successfully."),
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
    public function getHKIDataGroupedByCategory()
    {
        $query = HKI::with('authors');

        if (request()->has('study_program_id')) {
            $study_program_id = request()->input('study_program_id');
            $query->whereHas('authors', function ($q) use ($study_program_id) {
                $q->where('study_program_id', $study_program_id);
            });
        }

        $data = $query->get();
        $grouped_data = $data->groupBy('kategori')->map(function ($group) {
            return [
                'count' => $group->count(),
            ];
        });

        return $this->successResponse($grouped_data, 'Data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/hki/chart-data",
     *     summary="Get hki statistics chart data",
     *     description="Retrieves hki statistics grouped by study programs for chart visualization",
     *     tags={"HKI"},
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
     *             @OA\Property(property="message", type="string", example="HKI chart data retrieved successfully."),
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
     *                 @OA\Property(property="total_hkis", type="integer", example=40)
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
    public function getHKIChartData()
    {
        // Base query starting with study programs
        $hkis_by_program = StudyProgram::select(
            'study_programs.name as study_program',
            DB::raw('COALESCE(COUNT(DISTINCT hkis.id), 0) as total_hkis')
        )
            ->leftJoin('authors', 'study_programs.id', '=', 'authors.study_program_id')
            ->leftJoin('author_hki', 'authors.id', '=', 'author_hki.author_id')
            ->leftJoin('hkis', 'author_hki.hki_id', '=', 'hkis.id');

        // Apply year filter if provided
        if (request()->has('year')) {
            $year = request()->input('year');
            $hkis_by_program->where('hkis.tahun_permohonan', $year);
        }

        // Complete the query
        $hkis_by_program = $hkis_by_program
            ->groupBy('study_programs.id', 'study_programs.name')
            ->orderBy('study_programs.name')
            ->get();

        // Calculate total hkis
        $total_hkis = $hkis_by_program->sum('total_hkis');

        // Prepare chart data
        $chart_data = [
            'labels' => $hkis_by_program->pluck('study_program')->toArray(),
            'datasets' => [
                'data' => $hkis_by_program->pluck('total_hkis')->toArray(),
                'background_color' => $this->generateColors(count($hkis_by_program))
            ],
            'study_programs' => $hkis_by_program->map(function ($item) use ($total_hkis) {
                return [
                    'name' => $item->study_program,
                    'total' => $item->total_hkis,
                    'percentage' => $total_hkis > 0 ? round(($item->total_hkis / $total_hkis) * 100, 2) : 0,
                ];
            }),
            'total_hkis' => $total_hkis
        ];

        return $this->successResponse($chart_data, 'HKI chart data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/hki/{id}",
     *     summary="Delete a hki",
     *     description="Deletes a hki record by ID",
     *     security={{"bearer_token": {}}},
     *     tags={"HKI"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of hki to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="HKI deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="HKI data deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="HKI not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="HKI data not found.")
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
        $data = HKI::find($id);
        if (!$data) {
            return $this->errorResponse('Data not found.', 404);
        }

        $data->delete();

        return $this->successResponse(null, 'Data deleted successfully.', 200);
    }
}
