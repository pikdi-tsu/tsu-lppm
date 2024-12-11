<?php

namespace App\Http\Controllers;

use App\Exports\ServiceExport;
use App\Imports\ServiceImport;
use App\Models\Author;
use App\Models\Service;
use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use App\Traits\FunctionalMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ServiceController extends Controller
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
        $this->middleware(['role:superadmin|admin'])->except(['getServicesGroupedByScheme', 'getServicesChartData']);
    }

    /**
     * @OA\Post(
     *     path="/api/services/import",
     *     summary="Import services data from Excel/CSV file",
     *     tags={"Services"},
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
     *                     description="Excel/CSV file containing services data"
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
     *         description="Services imported successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Services data imported successfully."),
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
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        try {
            $import = new ServiceImport();

            if ($request->boolean('reset_table')) {
                DB::table('author_service')->delete();
                DB::table('services')->delete();
            }

            Excel::import($import, $request->file('file'));

            return $this->successResponse(null, 'Services data imported successfully.', 201);
        } catch (ValidationException $e) {
            $failures = $e->failures();

            return $this->importValidationErrorsResponse($failures, 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function export()
    {
        try {
            return Excel::download(new ServiceExport, 'lppm-service.xlsx');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/services",
     *     tags={"Services"},
     *     summary="Create new service",
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
     *             example={"nama_ketua": "Yustina", "nidn_ketua": "2342453", "afiliasi_ketua": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "kd_pt_ketua": "063040", "judul": "Pengabdian 1", "nama_singkat_skema": "PKS", "thn_pertama_usulan": "2024", "thn_usulan_kegiatan": "2024", "thn_pelaksanaan_kegiatan": "2024", "lama_kegiatan": "1", "bidang_fokus": "Teknologi Informasi dan Komunikasi", "nama_skema": "PENGABDIAN KERJASAMA", "status_usulan": "Disetujui", "approved_funds": 1200000, "afiliasi_sinta_id": "123456789", "nama_institusi_penerima_dana": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "target_tkt": "8", "nama_program_hibah": "PENGABDIAN KERJASAMA", "kategori_sumber_dana": "Perusahaan/Organisasi", "negara_sumber_dana": "ID", "sumber_dana": "PERUSAHAAN", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Service Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                  @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                  @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                  @OA\Property(property="judul", type="string", example="Pengabdian 1"),
     *                  @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                  @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                  @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                  @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                  @OA\Property(property="nama_skema", type="string", example="PENGABDIAN KERJASAMA"),
     *                  @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                  @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                  @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                  @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="target_tkt", type="string", example="8"),
     *                  @OA\Property(property="nama_program_hibah", type="string", example="PENGABDIAN KERJASAMA"),
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

        $service = Service::create($request->all());
        $service->authors()->attach($author->id);
        $service->authors()->attach($request->author_members);
        $service->save();

        $service->load('authors');

        return $this->successResponse($service, 'Service created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/services/{id}",
     *     tags={"Services"},
     *     summary="Update an service by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service",
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
     *             example={"nama_ketua": "Yustina", "nidn_ketua": "2342453", "afiliasi_ketua": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "kd_pt_ketua": "063040", "judul": "Pengabdian 1", "nama_singkat_skema": "PKS", "thn_pertama_usulan": "2024", "thn_usulan_kegiatan": "2024", "thn_pelaksanaan_kegiatan": "2024", "lama_kegiatan": "1", "bidang_fokus": "Teknologi Informasi dan Komunikasi", "nama_skema": "PENGABDIAN KERJASAMA", "status_usulan": "Disetujui", "approved_funds": 1200000, "afiliasi_sinta_id": "123456789", "nama_institusi_penerima_dana": "Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara", "target_tkt": "8", "nama_program_hibah": "PENGABDIAN KERJASAMA", "kategori_sumber_dana": "Perusahaan/Organisasi", "negara_sumber_dana": "ID", "sumber_dana": "PERUSAHAAN", "authors": {"0": 2, "1": 4, "2": 8} }
     *         )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service Updated",
     *          @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                  @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                  @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                  @OA\Property(property="judul", type="string", example="Pengabdian 1"),
     *                  @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                  @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                  @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                  @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                  @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                  @OA\Property(property="nama_skema", type="string", example="PENGABDIAN KERJASAMA"),
     *                  @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                  @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                  @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                  @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                  @OA\Property(property="target_tkt", type="string", example="8"),
     *                  @OA\Property(property="nama_program_hibah", type="string", example="PENGABDIAN KERJASAMA"),
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

        $service = Service::find($id);
        if (!$service) {
            return $this->errorResponse('Service not found.', 404);
        }

        $author = Author::where('nidn', $request->input('nidn_ketua'))->first();
        if (!$author) {
            return $this->errorResponse('Author with NIDN ' . $author->nidn_ketua . ' not found.', 404);
        }

        $service->update($request->all());
        $service->authors()->sync([$author->id]);
        $service->authors()->syncWithoutDetaching($request->author_members);
        $service->save();

        $service->load('authors');

        return $this->successResponse($service, 'Service updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/services",
     *     summary="Get paginated list of services",
     *     description="Returns paginated services with optional search functionality",
     *     security={{"bearer_token": {}}},
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term for filtering services by title, leaders nidn or leader name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Services data retrieved successfully."),
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
     *                         @OA\Property(property="judul", type="string", example="Pengabdian 1"),
     *                         @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                         @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                         @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                         @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                         @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                         @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                         @OA\Property(property="nama_skema", type="string", example="PENGABDIAN KERJASAMA"),
     *                         @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                         @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                         @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                         @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                         @OA\Property(property="target_tkt", type="string", example="8"),
     *                         @OA\Property(property="nama_program_hibah", type="string", example="PENGABDIAN KERJASAMA"),
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
    public function getServices()
    {
        $query = Service::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->whereAny(['nama_ketua', 'nidn_ketua'], 'like', "%$search_term%");
        }

        $services = $query->with('authors')->latest()->paginate(10);

        return $this->paginatedResponse($services, 'Services data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/services/{id}",
     *     summary="Get service by ID",
     *     description="Returns a specific service by its ID with related authors",
     *     security={{"bearer_token": {}}},
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of service to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nama_ketua", type="string", example="Yustina"),
     *                 @OA\Property(property="nidn_ketua", type="string", example="2342453"),
     *                 @OA\Property(property="afiliasi_ketua", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                 @OA\Property(property="kd_pt_ketua", type="string", example="063040"),
     *                 @OA\Property(property="judul", type="string", example="Pengabdian 1"),
     *                 @OA\Property(property="nama_singkat_skema", type="string", example="PKS"),
     *                 @OA\Property(property="thn_pertama_usulan", type="string", example="2024"),
     *                 @OA\Property(property="thn_usulan_kegiatan", type="string", example="2024"),
     *                 @OA\Property(property="thn_pelaksanaan_kegiatan", type="string", example="2024"),
     *                 @OA\Property(property="lama_kegiatan", type="string", example="1"),
     *                 @OA\Property(property="bidang_fokus", type="string", example="Teknologi Informasi dan Komunikasi"),
     *                 @OA\Property(property="nama_skema", type="string", example="PENGABDIAN KERJASAMA"),
     *                 @OA\Property(property="status_usulan", type="string", example="Disetujui"),
     *                 @OA\Property(property="dana_disetujui", type="string", example="Rp 1.200.000,00"),
     *                 @OA\Property(property="afiliasi_sinta_id", type="string", example="123456789"),
     *                 @OA\Property(property="nama_institusi_penerima_dana", type="string", example="Sekolah Tinggi Manajemen Informatika dan Komputer Sinar Nusantara"),
     *                 @OA\Property(property="target_tkt", type="string", example="8"),
     *                 @OA\Property(property="nama_program_hibah", type="string", example="PENGABDIAN KERJASAMA"),
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
     *         description="Service not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Service data not found.")
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
    public function getServiceByID($id)
    {
        $service = Service::with('authors')->find($id);
        if (!$service) {
            return $this->errorResponse('Service not found.', 404);
        }

        return $this->successResponse($service, 'Service data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/services/grouped-by-scheme",
     *     summary="Get services grouped by scheme",
     *     description="Retrieves services data grouped by `nama_singkat_skema`, with an optional filter by `study_program_id`.",
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="study_program_id",
     *         in="query",
     *         description="Filter services by study program ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful retrieval of services data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Services data retrieved successfully."),
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
    public function getServicesGroupedByScheme()
    {
        $query = Service::with('authors');

        if (request()->has('study_program_id')) {
            $study_program_id = request()->input('study_program_id');
            $query->whereHas('authors', function ($q) use ($study_program_id) {
                $q->where('study_program_id', $study_program_id);
            });
        }

        $services = $query->get();
        $grouped_data = $services->groupBy('nama_singkat_skema')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_funds' => Number::currency($group->sum('dana_disetujui'), 'IDR', 'id'),
            ];
        });

        return $this->successResponse($grouped_data, 'Services data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/services/chart-data",
     *     summary="Get services statistics chart data",
     *     description="Retrieves services statistics grouped by study programs for chart visualization",
     *     tags={"Services"},
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
     *             @OA\Property(property="message", type="string", example="Services chart data retrieved successfully."),
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
     *                 @OA\Property(property="total_services", type="integer", example=40)
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
    public function getServicesChartData()
    {
        // Base query starting with study programs
        $services_by_program = StudyProgram::select(
            'study_programs.name as study_program',
            DB::raw('COALESCE(COUNT(DISTINCT services.id), 0) as total_services')
        )
            ->leftJoin('authors', 'study_programs.id', '=', 'authors.study_program_id')
            ->leftJoin('author_service', 'authors.id', '=', 'author_service.author_id')
            ->leftJoin('services', 'author_service.service_id', '=', 'services.id');

        // Apply year filter if provided
        if (request()->has('year')) {
            $year = request()->input('year');
            $services_by_program->where('services.thn_pelaksanaan_kegiatan', $year);
        }

        // Complete the query
        $services_by_program = $services_by_program
            ->groupBy('study_programs.id', 'study_programs.name')
            ->orderBy('study_programs.name')
            ->get();

        // Calculate total services
        $total_services = $services_by_program->sum('total_services');

        // Prepare chart data
        $chart_data = [
            'labels' => $services_by_program->pluck('study_program')->toArray(),
            'datasets' => [
                'data' => $services_by_program->pluck('total_services')->toArray(),
                'background_color' => $this->generateColors(count($services_by_program))
            ],
            'study_programs' => $services_by_program->map(function ($item) use ($total_services) {
                return [
                    'name' => $item->study_program,
                    'total' => $item->total_services,
                    'percentage' => $total_services > 0 ? round(($item->total_services / $total_services) * 100, 2) : 0,
                ];
            }),
            'total_services' => $total_services
        ];

        return $this->successResponse($chart_data, 'Services chart data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/services/{id}",
     *     summary="Delete a service",
     *     description="Deletes a service record by ID",
     *     security={{"bearer_token": {}}},
     *     tags={"Services"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of service to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Service deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Service data deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Service not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Service data not found.")
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
        $service = Service::find($id);
        if (!$service) {
            return $this->errorResponse('Service not found.', 404);
        }

        $service->delete();

        return $this->successResponse(null, 'Service deleted successfully.', 200);
    }
}
