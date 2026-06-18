<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware(['role:superadmin|admin'])->except(['getSettingsByCategory']);
    }

    /**
     * @OA\Post(
     *     path="/api/settings",
     *     summary="Create new setting",
     *     tags={"Settings"},
     *     security={{"bearer_token":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="category", type="string", enum={"logo_header","logo_footer","social_media","address","contact"}),
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="description", type="string", maxLength=255),
     *                 @OA\Property(property="link_url", type="string", format="url", maxLength=255),
     *                 @OA\Property(property="image", type="file", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Setting created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings data created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="link_url", type="string"),
     *                 @OA\Property(property="image_path", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function create(Request $request)
    {
        $url_validation = $request->category == 'social_media'
            ? 'required|url|max:255'
            : 'nullable|max:255';

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:logo_header,logo_footer,social_media,address,contact',
            'name' => 'required|string|max:255',
            'description' => 'required_if:category,logo_header,logo_footer|string|max:255',
            'link_url' => $url_validation,
            'image' => 'required_if:category,logo_header,logo_footer,social_media|mimes:png'
        ]);
        
        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->file('image')) {
            $image = $request->file('image');
            $image_extension = $image->getClientOriginalExtension();
            $image_name = time() . '_' . $request->name . '.' . $image_extension;
            $image_path = $image->storeAs('images/settings', $image_name, 'public');
        }

        $data = Setting::create([
            'category' => $request->category,
            'name' => $request->name,
            'description' => $request->description,
            'link_url' => $request->link_url,
            'image_path' => $image_path ?? ''
        ]);

        return $this->successResponse($data, 'Settings data created successfully.', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/settings/{id}",
     *     summary="Update existing setting",
     *     tags={"Settings"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="category", type="string", enum={"logo_header","logo_footer","social_media","address","contact"}),
     *                 @OA\Property(property="name", type="string", maxLength=255),
     *                 @OA\Property(property="description", type="string", maxLength=255),
     *                 @OA\Property(property="link_url", type="string", format="url", maxLength=255),
     *                 @OA\Property(property="image", type="file", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Setting data updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="link_url", type="string"),
     *                 @OA\Property(property="image_path", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Setting not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $setting = Setting::find($id);
        if (!$setting) {
            return $this->errorResponse('Setting not found.', 404);
        }
        
        $image_rules = $setting->image_path ? 'nullable|mimes:png' : 'required_if:category,logo_header,logo_footer,social_media|mimes:png';
        
        $url_validation = $request->category == 'social_media'
            ? 'required|url|max:255'
            : 'nullable|max:255';

        $validator = Validator::make($request->all(), [
            'category' => 'required|in:logo_header,logo_footer,social_media,address,contact',
            'name' => 'required|string|max:255',
            'description' => 'required_if:category,logo_header,logo_footer|string|max:255',
            'link_url' => $url_validation,
            'image' => $image_rules
        ]);
        
        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($request->file('image')) {
            if ($setting->getRawOriginal('image_path') && Storage::disk('public')->exists($setting->getRawOriginal('image_path'))) {
                Storage::disk('public')->delete($setting->getRawOriginal('image_path'));
            }

            $image = $request->file('image');
            $image_extension = $image->getClientOriginalExtension();
            $image_name = time() . '_' . $request->name . '.' . $image_extension;
            $image_path = $image->storeAs('images/settings', $image_name, 'public');
        }

        $setting->update([
            'category' => $request->category,
            'name' => $request->name,
            'description' => $request->description,
            'link_url' => $request->link_url,
            'image_path' => $image_path ?? $setting->getRawOriginal('image_path')
        ]);

        return $this->successResponse($setting, 'Setting data updated successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/settings",
     *     summary="Get all settings with pagination",
     *     tags={"Settings"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="category", type="string"),
     *                         @OA\Property(property="name", type="string"),
     *                         @OA\Property(property="description", type="string"),
     *                         @OA\Property(property="link_url", type="string"),
     *                         @OA\Property(property="image_path", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getSettings()
    {
        $query = Setting::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->where('name', 'like', "%$search_term%");
        }

        $data = $query->paginate(10);

        return $this->successResponse($data, 'Settings data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/settings/{id}",
     *     summary="Get setting by ID",
     *     tags={"Settings"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Setting data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="link_url", type="string"),
     *                 @OA\Property(property="image_path", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Setting not found")
     * )
     */
    public function getSettingByID($id)
    {
        $setting = Setting::find($id);
        if (!$setting) {
            return $this->errorResponse('Setting not found.', 404);
        }

        return $this->successResponse($setting, 'Setting data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/settings/by-category/{category}",
     *     summary="Get settings by category",
     *     tags={"Settings"},
     *     @OA\Parameter(
     *         name="category",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"logo_header","logo_footer","social_media","address","contact"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Settings data retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="category", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="link_url", type="string"),
     *                     @OA\Property(property="image_path", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Settings not found")
     * )
     */
    public function getSettingsByCategory($category)
    {
        $settings = Setting::where('category', $category)->get();
        if ($settings->count() < 1) {
            return $this->errorResponse('Settings data with selected category not found.', 404);
        }

        return $this->successResponse($settings, 'Settings data retrieved successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/settings/{id}",
     *     summary="Delete setting",
     *     tags={"Settings"},
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Setting data deleted successfully."),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Setting not found")
     * )
     */
    public function delete($id)
    {
        $setting = Setting::find($id);
        if (!$setting) {
            return $this->errorResponse('Setting not found.', 404);
        }

        if ($setting->getRawOriginal('image_path') && Storage::disk('public')->exists($setting->getRawOriginal('image_path'))) {
            Storage::disk('public')->delete($setting->getRawOriginal('image_path'));
        }

        $setting->delete();

        return $this->successResponse(null, 'Setting ' . $setting->name . ' deleted successfully.', 200);
    }
}
