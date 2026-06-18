<?php

namespace App\Http\Controllers;

use App\Models\StudyProgram;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class StudyProgramController extends Controller
{
    use ApiResponse;


    public function __construct()
    {
        $this->middleware(['role:superadmin|admin'])->except(['getStudyProgramList']);
    }

    /**
     * @OA\Post(
     *     path="/api/study-programs",
     *     tags={"Study Programs"},
     *     summary="Create new study program",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="name",
     *                 type="string"
     *             ),
     *             example={"name": "S1 Informatika"}
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Study Program Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Study program created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="S1 Informatika"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The name field is required."),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="title", type="array",
     *                      @OA\Items(
     *                          type="string", example="The name field is required."
     *                      )
     *                  )
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $study_programs = StudyProgram::create([
            'name' => $request->name
        ]);

        return $this->successResponse($study_programs, 'Study program created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/study-programs/{id}",
     *     tags={"Study Programs"},
     *     summary="Update an study program by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the study program",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                 property="name",
     *                 type="string"
     *             ),
     *             example={"name": "S1 Sistem Informasi"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Study Program Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Study program successfully updated."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="S1 Sistem Informasi"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The name field is required."),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="title", type="array",
     *                      @OA\Items(
     *                          type="string", example="The name field is required."
     *                      )
     *                  ),
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        $study_program = StudyProgram::find($id);
        $study_program->update([
            'name' => $request->name
        ]);

        return $this->successResponse($study_program, 'Study program data updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/study-programs",
     *     summary="Get list of study programs",
     *     tags={"Study Programs"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search study programs by name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Study programs data retrieved successfully."),
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
     *                         @OA\Property(property="name", type="string", example="Computer Science"),
     *                         @OA\Property(
     *                             property="authors",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="John Doe")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=10)
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
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function getStudyPrograms()
    {
        $query = StudyProgram::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query = $query->where('name', 'like', '%' . $search_term . '%');
        }

        $study_programs = $query->with('authors')->paginate(10);

        return $this->successResponse($study_programs, 'Study programs data retrieved successfully.', 200);
    }

    public function getStudyProgramList()
    {
        $data = StudyProgram::select(['id', 'name'])->get();

        return $this->successResponse($data, 'Study program list retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/study-programs/{id}",
     *     summary="Get study program by ID",
     *     tags={"Study Programs"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of study program",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Study program data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="S1 Informatika"),
     *                 @OA\Property(
     *                     property="authors",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Hendro Wijayanto")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Study program not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Study program not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function getStudyProgramByID($id)
    {
        $study_program = StudyProgram::with('authors')->find($id);
        if (!$study_program) {
            return $this->errorResponse('Study program not found.', 404);
        }

        return $this->successResponse($study_program, 'Study program data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/study-programs/{id}",
     *     summary="Delete a study program",
     *     tags={"Study Programs"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of study program to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Study program deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Study program deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Study program not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Study program not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized Access",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized"),
     *         )
     *     ),
     * )
     */
    public function delete($id)
    {
        $study_program = StudyProgram::find($id);
        if (!$study_program) {
            return $this->errorResponse('Study program not found.', 404);
        }

        $study_program->delete();

        return $this->successResponse(null, 'Study program ' . $study_program->name . ' deleted successfully.', 200);
    }
}
