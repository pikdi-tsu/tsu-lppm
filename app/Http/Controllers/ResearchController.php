<?php

namespace App\Http\Controllers;

use App\Exports\ResearchExport;
use App\Imports\ResearchImport;
use App\Models\Author;
use App\Models\Research;
use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use App\Traits\FunctionalMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ResearchController extends Controller
{
    use ApiResponse, FunctionalMethod;
    protected $validation_rules = [
        'nama_ketua' => 'required|string|max:255',
        'nidn_ketua' => 'required|string|max:100',
        'afiliasi_ketua' => 'required|string|max:255',
        'kd_pt_ketua' => 'required|string|max:50',
        'judul' => 'required|string|max:255',
        'nama_singkat_skema' => 'required|string|max:50',
        'thn_pertama_usulan' => 'required|string|max:4',
        'thn_usulan_kegiatan' => 'required|string|max:4',
        'thn_pelaksanaan_kegiatan' => 'required|string|max:4',
        'lama_kegiatan' => 'required|string|max:4',
        'bidang_fokus' => 'required|string|max:100',
        'nama_skema' => 'required|string|max:100',
        'status_usulan' => 'required|string|max:50',
        'dana_disetujui' => 'required|integer',
        'afiliasi_sinta_id' => 'required|string|max:4',
        'nama_institusi_penerima_dana' => 'required|string|max:255',
        'target_tkt' => 'required|string|max:20',
        'nama_program_hibah' => 'required|string|max:100',
        'kategori_sumber_dana' => 'required|string|max:50',
        'negara_sumber_dana' => 'required|string|max:50',
        'sumber_dana' => 'required|string|max:50',
        'author_members' => 'nullable|array',
        'author_members.*' => 'exists:authors,id',
    ];


    public function __construct()
    {
        $this->middleware(['role:superadmin|admin'])->except(['getResearchesGroupedByScheme', 'getResearchesChartData']);
    }

    /**
     * @OA\Post(
     *     path="/api/researches/import",
     *     summary="Import researches data from Excel/CSV file",
     *     tags={"Researches"},
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
     *                     description="Excel/CSV file containing researches data"
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
     *         description="Researches imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Researches data imported successfully."),
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
            'file' => 'required|file|mimes:csv,xls,xlsx',
            'reset_table' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        try {
            $import = new ResearchImport();

            if ($request->boolean('reset_table')) {
                DB::table('author_research')->delete();
                DB::table('researches')->delete();
            }

            Excel::import($import, $request->file('file'));

            return $this->successResponse(null, 'Research data imported successfully.', 201);
        } catch (ValidationException $e) {
            $failures = $e->failures();

            return $this->importValidationErrorsResponse($failures, 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/researches/export",
     *     tags={"Researches"},
     *     summary="Export research data to Excel",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Excel file download",
     *         @OA\Header(
     *             header="Content-Type",
     *             description="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
     *         ),
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="attachment; filename=lppm-researches.xlsx"
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
            return Excel::download(new ResearchExport, 'lppm-researches.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/researches",
     *     tags={"Researches"},
     *     summary="Create new research",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="nama_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nidn_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="afiliasi_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kd_pt_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="judul",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_singkat_skema",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_pertama_usulan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_usulan_kegiatan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_pelaksanaan_kegiatan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="lama_kegitan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="bidang_fokus",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_skema",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="status_usulan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="approved_funds",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="afiliasi_sinta_id",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_institusi_penerima_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="target_tkt",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_program_hibah",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kategori_sumber_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="negara_sumber_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="sumber_dana",
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
     *             example={"nama_ketua": "Yustina", "nidn_ketua": "2342453", "afiliasi_ketua": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "kd_pt_ketua": "063040", "judul": "Penelitian 1", "nama_singkat_skema": "PKS", "thn_pertama_usulan": "2024", "thn_usulan_kegiatan": "2024", "thn_pelaksanaan_kegiatan": "2024", "lama_kegiatan": "1", "bidang_fokus": "Teknologi Informasi dan Komunikasi", "nama_skema": "PENELITIAN KERJASAMA", "status_usulan": "Disetujui", "approved_funds": 1200000, "afiliasi_sinta_id": "123456789", "nama_institusi_penerima_dana": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "target_tkt": "8", "nama_program_hibah": "PENELITIAN KERJASAMA", "kategori_sumber_dana": "Perusahaan/Organisasi", "negara_sumber_dana": "ID", "sumber_dana": "PERUSAHAAN", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Research Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Research created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                  @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                  @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                  @OA\Property(property="judul", type="string", example="Penelitian 1"),
     *                  @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                  @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                  @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                  @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                  @OA\Property(property="nama_skema", type="string", example="PENELITIAN KERJASAMA"),
     *                  @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                  @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                  @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                  @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="target_tkt", type="string", example="8"),
     *                  @OA\Property(property="nama_program_hibah", type="string", example="PENELITIAN KERJASAMA"),
     *                  @OA\Property(property="kategori_sumber_dana", type="string", example="Perusahaan/Organisasi"),
     *                  @OA\Property(property="negara_sumber_dana", type="string", example="ID"),
     *                  @OA\Property(property="sumber_dana", type="string", example="PERUSAHAAN"),
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
        $validator = Validator::make($request->all(), $this->validation_rules);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $author = Author::where('nidn', $request->input('nidn_ketua'))->first();
        if (!$author) {
            return $this->errorResponse('Author not found.', 404);
        }

        $research = Research::create($request->all());
        $research->authors()->attach($author->id);
        $research->authors()->attach($request->author_members);
        $research->save();

        $research->load('authors');

        return $this->successResponse($research, 'Research created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/researches/{id}",
     *     tags={"Researches"},
     *     summary="Update an research by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the research",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *             @OA\Property(
     *                 property="nama_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nidn_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="afiliasi_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kd_pt_ketua",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="judul",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_singkat_skema",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_pertama_usulan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_usulan_kegiatan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="thn_pelaksanaan_kegiatan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="lama_kegitan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="bidang_fokus",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_skema",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="status_usulan",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="approved_funds",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="afiliasi_sinta_id",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_institusi_penerima_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="target_tkt",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="nama_program_hibah",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="kategori_sumber_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="negara_sumber_dana",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="sumber_dana",
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
     *             example={"nama_ketua": "Yustina", "nidn_ketua": "2342453", "afiliasi_ketua": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "kd_pt_ketua": "063040", "judul": "Penelitian 1", "nama_singkat_skema": "PKS", "thn_pertama_usulan": "2024", "thn_usulan_kegiatan": "2024", "thn_pelaksanaan_kegiatan": "2024", "lama_kegiatan": "1", "bidang_fokus": "Teknologi Informasi dan Komunikasi", "nama_skema": "PENELITIAN KERJASAMA", "status_usulan": "Disetujui", "approved_funds": 1200000, "afiliasi_sinta_id": "123456789", "nama_institusi_penerima_dana": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "target_tkt": "8", "nama_program_hibah": "PENELITIAN KERJASAMA", "kategori_sumber_dana": "Perusahaan/Organisasi", "negara_sumber_dana": "ID", "sumber_dana": "PERUSAHAAN", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Research Updated",
     *          @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Research updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                  @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                  @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                  @OA\Property(property="judul", type="string", example="Penelitian 1"),
     *                  @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                  @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                  @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                  @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                  @OA\Property(property="nama_skema", type="string", example="PENELITIAN KERJASAMA"),
     *                  @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                  @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                  @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                  @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="target_tkt", type="string", example="8"),
     *                  @OA\Property(property="nama_program_hibah", type="string", example="PENELITIAN KERJASAMA"),
     *                  @OA\Property(property="kategori_sumber_dana", type="string", example="Perusahaan/Organisasi"),
     *                  @OA\Property(property="negara_sumber_dana", type="string", example="ID"),
     *                  @OA\Property(property="sumber_dana", type="string", example="PERUSAHAAN"),
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
        $validator = Validator::make($request->all(), $this->validation_rules);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $research = Research::find($id);
        if (!$research) {
            return $this->errorResponse('Research not found.', 404);
        }

        $author = Author::where('nidn', $request->input('nidn_ketua'))->first();
        if (!$author) {
            return $this->errorResponse('Author with NIDN ' . $author->nidn_ketua . ' not found.', 404);
        }

        $research->update($request->all());
        $research->authors()->sync([$author->id]);
        $research->authors()->syncWithoutDetaching($request->author_members);
        $research->save();

        $research->load('authors');

        return $this->successResponse($research, 'Research updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/researches",
     *     summary="Get paginated list of researches",
     *     description="Returns paginated researches with optional search functionality",
     *     security={{"bearer_token": {}}},
     *     tags={"Researches"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for filtering researches by title, leaders nidn or leader name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Researches data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                         @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                         @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                         @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                         @OA\Property(property="judul", type="string", example="Penelitian 1"),
     *                         @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                         @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                         @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                         @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                         @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                         @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                         @OA\Property(property="nama_skema", type="string", example="PENELITIAN KERJASAMA"),
     *                         @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                         @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                         @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                         @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                         @OA\Property(property="target_tkt", type="string", example="8"),
     *                         @OA\Property(property="nama_program_hibah", type="string", example="PENELITIAN KERJASAMA"),
     *                         @OA\Property(property="kategori_sumber_dana", type="string", example="Perusahaan/Organisasi"),
     *                         @OA\Property(property="negara_sumber_dana", type="string", example="ID"),
     *                         @OA\Property(property="sumber_dana", type="string", example="PERUSAHAAN"),
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
    public function getResearches()
    {
        $query = Research::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->whereAny(['nama_ketua', 'nidn_ketua', 'judul'], 'like', "%$search_term%");
        }

        $researches = $query->with('authors')->paginate(10);

        $researches->getCollection()->transform(function ($research) {
            $research->dana_disetujui = $this->currencyFormat($research->dana_disetujui);
            return $research;
        });

        return $this->paginatedResponse($researches, 'Researches data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/researches/{id}",
     *     summary="Get research by ID",
     *     description="Returns a specific research by its ID with related authors",
     *     security={{"bearer_token": {}}},
     *     tags={"Researches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of research to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Research data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                 @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                 @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                 @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                 @OA\Property(property="judul", type="string", example="Penelitian 1"),
     *                 @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                 @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                 @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                 @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                 @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                 @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                 @OA\Property(property="nama_skema", type="string", example="PENELITIAN KERJASAMA"),
     *                 @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                 @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                 @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                 @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                 @OA\Property(property="target_tkt", type="string", example="8"),
     *                 @OA\Property(property="nama_program_hibah", type="string", example="PENELITIAN KERJASAMA"),
     *                 @OA\Property(property="kategori_sumber_dana", type="string", example="Perusahaan/Organisasi"),
     *                 @OA\Property(property="negara_sumber_dana", type="string", example="ID"),
     *                 @OA\Property(property="sumber_dana", type="string", example="PERUSAHAAN"),
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
     *         description="Research not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Research data not found.")
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
    public function getResearchByID($id)
    {
        $research = Research::with('authors')->find($id);
        if (!$research) {
            return $this->errorResponse('Research not found.', 404);
        }

        $research->dana_disetujui = $this->currencyFormat($research->dana_disetujui);

        return $this->successResponse($research, 'Research data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/researches/grouped-by-scheme",
     *     summary="Get researches grouped by scheme",
     *     description="Retrieves researches data grouped by `nama_singkat_skema`, with an optional filter by `study_program_id`.",
     *     tags={"Researches"},
     *     @OA\Parameter(
     *         name="study_program_id",
     *         in="query",
     *         description="Filter researches by study program ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of research data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Researches data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="PI", type="object",
     *                     @OA\Property(property="count", type="integer", example=2),
     *                     @OA\Property(property="total_funds", type="string", example="Rp 24.000.000,00"),
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
    public function getResearchesGroupedByScheme()
    {
        $query = Research::with('authors');

        if (request()->has('study_program_id')) {
            $study_program_id = request()->input('study_program_id');
            $query->whereHas('authors', function ($q) use ($study_program_id) {
                $q->where('study_program_id', $study_program_id);
            });
        }

        $researches = $query->get();
        $grouped_data = $researches->groupBy('nama_singkat_skema')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_funds' => $this->currencyFormat($group->sum('dana_disetujui'))
            ];
        });

        return $this->successResponse($grouped_data, 'Researches data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/researches/chart-data",
     *     summary="Get research statistics chart data",
     *     description="Retrieves research statistics grouped by study programs for chart visualization",
     *     tags={"Researches"},
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
     *             @OA\Property(property="message", type="string", example="Researches chart data retrieved successfully."),
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
     *                 @OA\Property(property="total_researches", type="integer", example=40)
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
    public function getResearchesChartData()
    {
        // Base query starting with study programs
        $researches_by_program = StudyProgram::select(
            'study_programs.name as study_program',
            DB::raw('COALESCE(COUNT(DISTINCT researches.id), 0) as total_researches')
        )
            ->leftJoin('authors', 'study_programs.id', '=', 'authors.study_program_id')
            ->leftJoin('author_research', 'authors.id', '=', 'author_research.author_id')
            ->leftJoin('researches', 'author_research.research_id', '=', 'researches.id');

        // Apply year filter if provided
        if (request()->has('year')) {
            $year = request()->input('year');
            $researches_by_program->where('researches.thn_pelaksanaan_kegiatan', $year);
        }

        // Complete the query
        $researches_by_program = $researches_by_program
            ->groupBy('study_programs.id', 'study_programs.name')
            ->orderBy('study_programs.name')
            ->get();

        // Calculate total researches
        $total_researches = $researches_by_program->sum('total_researches');

        // Prepare chart data
        $chart_data = [
            'labels' => $researches_by_program->pluck('study_program')->toArray(),
            'datasets' => [
                'data' => $researches_by_program->pluck('total_researches')->toArray(),
                'background_color' => $this->generateColors(count($researches_by_program))
            ],
            'study_programs' => $researches_by_program->map(function ($item) use ($total_researches) {
                return [
                    'name' => $item->study_program,
                    'total' => $item->total_researches,
                    'percentage' => $total_researches > 0 ? round(($item->total_researches / $total_researches) * 100, 2) : 0,
                ];
            }),
            'total_researches' => $total_researches
        ];

        return $this->successResponse($chart_data, 'Researches chart data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/researches/{id}",
     *     summary="Delete a research",
     *     description="Deletes a research record by ID",
     *     security={{"bearer_token": {}}},
     *     tags={"Researches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of research to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Research deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service data deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Research not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Research data not found.")
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
        $research = Research::find($id);
        if (!$research) {
            return $this->errorResponse('Research data not found.', 404);
        }

        $research->delete();

        return $this->successResponse(null, 'Research data deleted successfully.', 200);
    }
}
