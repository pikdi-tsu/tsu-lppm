<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    use ApiResponse;


    public function __construct()
    {
        $this->middleware(['role:superadmin|admin'])->except(['getPostsByPageSlug', 'getPostsByParentSlug']);
    }

    /**
     * @OA\Post(
     *     path="/api/posts",
     *     tags={"Posts"},
     *     summary="Create new post",
     *     security={{"bearer_token":{}}},
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="container",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="page_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="category_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="image",
     *                 type="string",
     *                 format="binary"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string"
     *             ),
     *             example={"title": "Penelitian", "container": "Lorem ipsum dolor sit amet.", "page_id": 1, "category_id": 2, "status": "published"}
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=201,
     *         description="Post Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Post created successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="title", type="string", example="Penelitian"),
     *                  @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                  @OA\Property(property="page_id", type="integer", example=1),
     *                  @OA\Property(property="category_id", type="integer", example=2),
     *                  @OA\Property(property="image_url", type="string", example="public/images/132187847_layanan.img"),
     *                  @OA\Property(property="file_url", type="string", example=null),
     *                  @OA\Property(property="link_url", type="string", example=null),
     *                  @OA\Property(property="status", type="string", example="published"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The title field must be at least 3 characters"),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="title", type="array",
     *                      @OA\Items(
     *                          type="string", example="The title field must be at least 3 characters."
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
        $category_slug = Category::where('id', $request->category_id)->pluck('slug')->first();

        $container_rule = $category_slug === 'file' ? 'nullable' : 'required';

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:3|max:50|string',
            'container' => $container_rule,
            'page_id' => 'required|exists:pages,id',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|mimes:jpg,jpeg,png',
            'file' => 'nullable|mimes:pdf',
            'link_url' => 'nullable|url',
            'status' => 'required|in:published,draft'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($category_slug == 'media' && !$request->file('image')) {
            return $this->errorResponse('Image required for media category.', 400);
        }

        if ($category_slug == 'journal' && !$request->link_url) {
            return $this->errorResponse('Link URL required for journal category.', 400);
        }

        if ($category_slug == 'file' && !$request->file('file')) {
            return $this->errorResponse('File required for file category.', 400);
        }

        $page_slug = Page::where('id', $request->page_id)->pluck('slug')->first();

        $image_path = null;
        if ($request->file('image')) {
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $newImageName = time() . '_' . $page_slug . '.' . $extension;
            $image_path = $image->storeAs('images/posts', $newImageName, 'public');
        }

        $file_path = null;
        if ($request->file('file')) {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $newFileName = time() . '_' . $page_slug . '.' . $extension;
            $file_path = $file->storeAs('files/posts', $newFileName, 'public');
        }

        $author_id = Auth::user()->id;

        $post = Post::create([
            'title' => $request->title,
            'container' => $request->container,
            'author_id' => $author_id,
            'page_id' => $request->page_id,
            'category_id' => $request->category_id,
            'image_url' => $image_path,
            'file_url' => $file_path,
            'link_url' => $request->link_url,
            'status' => $request->status
        ]);

        return $this->successResponse($post, 'Post created successfully.', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/posts",
     *     tags={"Posts"},
     *     summary="Get posts data",
     *     security={{"bearer_token":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Get posts data successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Posts data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="data", 
     *                      type="array", 
     *                      example={{ 
     *                          "id": 1,
     *                          "title": "Visi",
     *                          "container": "Lorem ipsum dolor sit amet.",
     *                          "page_id": 1,
     *                          "category_id": 1,
     *                          "category_slug": "text",
     *                          "page_title": "Visi dan Misi",
     *                          "author_name": "lppm sinus",
     *                          "image_url": null,
     *                          "file_url": null,
     *                          "link_url": null,
     *                          "created_at": "2024-09-12T06:33:25.000000Z",
     *                          "updated_at": "2024-09-12T06:33:25.000000Z"
     *                      }, {
     *                          "id": 2,
     *                          "title": "Struktur",
     *                          "container": "Lorem ipsum dolor sit amet.",
     *                          "page_id": 1,
     *                          "category_id": 2,
     *                          "category_slug": "media",
     *                          "page_title": "Struktur",
     *                          "author_name": "lppm sinus 2",
     *                          "image_url": "public/images/posts/123141454_struktur",
     *                          "file_url": null,
     *                          "link_url": null,
     *                          "created_at": "2024-09-12T06:33:25.000000Z",
     *                          "updated_at": "2024-09-12T06:33:25.000000Z"
     *                      }},
     *                      @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=1),
     *                           @OA\Property(property="title", type="string", example="Visi"),
     *                           @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                           @OA\Property(property="page_id", type="integer", example=1),
     *                           @OA\Property(property="category_id", type="integer", example=1),
     *                           @OA\Property(property="category_slug", type="string", example="text"),
     *                           @OA\Property(property="page_title", type="string", example="Visi"),
     *                           @OA\Property(property="author_name", type="string", example="lppm sinus"),
     *                           @OA\Property(property="image_url", type="string", example=null),
     *                           @OA\Property(property="file_url", type="string", example=null),
     *                           @OA\Property(property="link_url", type="string", example=null),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                       ),
     *                       @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=2),
     *                           @OA\Property(property="title", type="string", example="Struktur"),
     *                           @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                           @OA\Property(property="page_id", type="integer", example=1),
     *                           @OA\Property(property="category_id", type="integer", example=2),
     *                           @OA\Property(property="category_slug", type="string", example="media"),
     *                           @OA\Property(property="page_title", type="string", example="Struktur"),
     *                           @OA\Property(property="author_name", type="string", example="lppm sinus 2"),
     *                           @OA\Property(property="image_url", type="string", example="public/images/posts/123141454_struktur"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                       )
     *                  ),
     *                  @OA\Property(property="first_page_url", type="string", example="http://localhost:8000/api/posts?page=1"),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="last_page", type="integer", example=2),
     *                  @OA\Property(property="last_page_url", type="string", example="http://localhost:8000/api/posts?page=2"),
     *                  @OA\Property(property="links", 
     *                      type="array", 
     *                      example={{ 
     *                          "url": null,
     *                          "label": "&laquo; Previous",
     *                          "active": false
     *                      }, {
     *                          "url": "http://localhost:8000/api/posts?page=1",
     *                          "label": "1",
     *                          "active": true
     *                      }, {
     *                          "url": null,
     *                          "label": "Next &raquo;",
     *                          "active": false
     *                      }},
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="url", type="string", example=null),
     *                          @OA\Property(property="label", type="string", example="&laquo; Previous"),
     *                          @OA\Property(property="active", type="boolean", example=false),
     *                      ),
     *                  ),
     *                  @OA\Property(property="next_page_url", type="string", example=null),
     *                  @OA\Property(property="path", type="string", example="http://localhost:8000/api/posts"),
     *                  @OA\Property(property="per_page", type="integer", example=10),
     *                  @OA\Property(property="prev_page", type="string", example=null),
     *                  @OA\Property(property="to", type="integer", example=4),
     *                  @OA\Property(property="total", type="integer", example=4)
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
     *              @OA\Property(property="message", type="string", example="Unauthorized."),
     *         )
     *     ),
     * )
     */
    public function getPosts()
    {
        $current_user = Auth::user();

        $query = Post::query();

        if (!$current_user->hasRole('superadmin')) {
            $query->where('author_id', $current_user->id);
        }

        if (request()->has('page_slug')) {
            $page_slug = request()->input('page_slug');
            $query->whereHas('page', function ($q) use ($page_slug) {
                $q->where('slug', $page_slug);
            });
        }

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->where('title', 'like', "%{$search_term}%");
        }

        // Get paginated results with relationships
        $posts = $query->with([
            'category:id,slug',
            'page:id,title',
            'author:id,name'
        ])
            ->select([
                'id',
                'title',
                'container',
                'category_id',
                'page_id',
                'author_id',
                'image_url',
                'file_url',
                'link_url',
                'status',
                'created_at',
                'updated_at'
            ])
            ->latest()
            ->paginate(10);

        // Transform the data
        $transformed_data = $posts->toArray();

        // Transform each post in the data array
        $transformed_data['data'] = collect($transformed_data['data'])->map(function ($post) {
            return [
                'id' => $post['id'],
                'title' => $post['title'],
                'container' => $post['container'],
                'category_id' => $post['category_id'],
                'category_slug' => $post['category']['slug'],
                'page_title' => $post['page']['title'],
                'author_name' => $post['author']['name'],
                'image_url' => $post['image_url'] ? Storage::url($post['image_url']) : null,
                'file_url' => $post['file_url'] ? Storage::url($post['file_url']) : null,
                'link_url' => $post['link_url'],
                'status' => $post['status'],
                'created_at' => $post['created_at'],
                'updated_at' => $post['updated_at']
            ];
        })->toArray();

        return $this->successResponse($transformed_data, 'Posts data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/posts/by_page/{page_slug}",
     *     tags={"Posts"},
     *     summary="Get a posts by page slug",
     *     @OA\Parameter(
     *         name="page_slug",
     *         in="path",
     *         required=true,
     *         description="The page slug of the post",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Posts data retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Page data retrieved successfully."),
     *             @OA\Property(property="data", 
     *                      type="array", 
     *                      example={{ 
     *                          "id": 1,
     *                          "title": "Penelitian",
     *                          "container": "Lorem ipsum dolor sit amet.",
     *                          "page_id": 1,
     *                          "category_id": 1,
     *                          "image_url": null,
     *                          "status": "published",
     *                          "created_at": "2024-09-12T06:33:25.000000Z",
     *                          "updated_at": "2024-09-12T06:33:25.000000Z"
     *                      }, {
     *                          "id": 2,
     *                          "title": "Pengabdian",
     *                          "container": "Lorem ipsum dolor sit amet.",
     *                          "page_id": 1,
     *                          "category_id": 2,
     *                          "image_url": "/storage/images/1727929205_pengabdian.png",
     *                          "status": "published",
     *                          "created_at": "2024-09-12T06:34:25.000000Z",
     *                          "updated_at": "2024-09-12T06:36:25.000000Z"
     *                      }},
     *                      @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=1),
     *                           @OA\Property(property="title", type="string", example="Penelitian"),
     *                           @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                           @OA\Property(property="page_id", type="integer", example=1),
     *                           @OA\Property(property="category_id", type="integer", example=1),
     *                           @OA\Property(property="image_url", type="string", example=null),
     *                           @OA\Property(property="status", type="string", example="published"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                       ),
     *                       @OA\Items(
     *                           type="object",
     *                           @OA\Property(property="id", type="integer", example=1),
     *                           @OA\Property(property="title", type="string", example="Pengabdian"),
     *                           @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                           @OA\Property(property="page_id", type="integer", example=1),
     *                           @OA\Property(property="category_id", type="integer", example=2),
     *                           @OA\Property(property="image_url", type="string", example="/storage/images/1727929205_pengabdian.png"),
     *                           @OA\Property(property="status", type="string", example="published"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                       )
     *                  ),
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Page slug not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Page slug not found."),
     *         )
     *     ),
     * )
     */
    public function getPostsByPageSlug($page_slug)
    {
        $page = Page::where('slug', $page_slug)->first();
        if (!$page) {
            return $this->errorResponse('Page slug not found.', 404);
        }

        $posts = Post::where('page_id', $page->id)
            ->where('status', 'published')
            ->with(['category:id,slug', 'page:id,title'])
            ->get(['id', 'title', 'container', 'category_id', 'page_id', 'image_url', 'file_url', 'link_url']);

        $posts = $posts->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'container' => $post->container,
                'category_id' => $post->category_id,
                'category_slug' => $post->category->slug,
                'page_title' => $post->page->title,
                'image_url' => $post->image_url ?? '',
                'file_url' => $post->file_url ?? '',
                'link_url' => $post->link_url,
            ];
        });

        return $this->successResponse($posts, 'Posts data retrieved successfully.', 200);
    }

    public function getPostsByParentSlug($page_slug)
    {
        $page = Page::where('parent_id', null)->where('slug', $page_slug)->first();
        if (!$page) {
            return $this->errorResponse('Page slug not found.', 404);
        }

        $pages_id = Page::where('parent_id', $page->id)->pluck('id')->toArray();
        array_push($pages_id, $page->id);

        if (request()->has('filter')) {
            $filter_id = request()->input('filter');
            $pages_id = [$filter_id];
        }

        $query = Post::query();

        if (request()->has('q')) {
            $search_term = request()->input('q');
            $query->where('title', 'like', "%$search_term%");
        }

        $posts = $query->whereIn('page_id', $pages_id)
            ->with(['category:id,slug', 'page:id,title'])->paginate(10);

        return $this->paginatedResponse($posts, 'Posts data retrieved successfully.', 200);
    }

    /**
     * @OA\Get(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Get a post by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the post",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post data retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Post data retrieved successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="title", type="string", example="Penelitian"),
     *                  @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                  @OA\Property(property="page_id", type="integer", example=1),
     *                  @OA\Property(property="category_id", type="integer", example=1),
     *                  @OA\Property(property="image_url", type="string", example="/storage/images/1727929205_pengabdian.png"),
     *                  @OA\Property(property="status", type="string", example="published"),
     *                  @OA\Property(property="created_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time", example="2024-09-12T06:33:25.000000Z"),
     *                  @OA\Property(property="category", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="Text"),
     *                      @OA\Property(property="slug", type="string", example="text"),
     *                  ),
     *                  @OA\Property(property="author", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="lppm sinus"),
     *                      @OA\Property(property="email", type="string", example="lppm@sinus.ac.id"),
     *                  ),
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
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Post not found.")
     *         )
     *     )
     * )
     */
    public function getPostByID($id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->errorResponse('Post not found.', 404);
        }

        $post = Post::where('id', $id)
            ->with(['category:id,name,slug', 'author:id,name,email'])
            ->first();

        return $this->successResponse($post, 'Post data retrieved successfully.', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Update a post by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the post",
     *         @OA\Schema(type="integer")
     *     ),
     *  @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(
     *                 property="title",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="container",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="page_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="category_id",
     *                 type="integer"
     *             ),
     *             @OA\Property(
     *                 property="image",
     *                 type="string",
     *                 format="binary"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string"
     *             ),
     *             @OA\Property(
     *                 property="_method",
     *                 type="string"
     *             ),
     *             example={"title": "Penelitian", "container": "Lorem ipsum dolor sit amet.", "page_id": 1, "category_id": 2, "status": "published", "method": "patch"}
     *         )
     *     )
     * ),
     *     @OA\Response(
     *         response=200,
     *         description="Post Updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Post updated successfully."),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="title", type="string", example="Penelitian"),
     *                  @OA\Property(property="container", type="string", example="Lorem ipsum dolor sit amet."),
     *                  @OA\Property(property="page_id", type="integer", example=1),
     *                  @OA\Property(property="category_id", type="integer", example=2),
     *                  @OA\Property(property="image_url", type="string", example="public/images/132184546_layanan.img"),
     *                  @OA\Property(property="status", type="string", example="published"),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The title field must be at least 3 characters"),
     *              @OA\Property(
     *                  property="errors", type="object",
     *                  @OA\Property(
     *                      property="title", type="array",
     *                      @OA\Items(
     *                          type="string", example="The title field must be at least 3 characters."
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
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example=false),
     *             @OA\Property(property="message", type="string", example="Post not found."),
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->errorResponse('Post not found.', 404);
        }

        // if (!auth()->user()->hasRole('superadmin') && auth()->user()->id !== $post->author_id) {
        //     return $this->errorResponse('You are not authorized to update this post.', 401);
        // }

        $category_slug = Category::where('id', $request->category_id)->pluck('slug')->first();

        $container_rule = $category_slug === 'file' ? 'nullable' : 'required';

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:3|max:50|string',
            'container' => $container_rule,
            'page_id' => 'required|exists:pages,id',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image',
            'file' => 'nullable|mimes:pdf',
            'link_url' => 'nullable|url',
            'status' => 'required|in:published,draft'
        ]);

        if ($validator->fails()) {
            return $this->formatValidationErrors($validator);
        }

        if ($category_slug == 'media' && !$request->file('image') && $post->image_url == null) {
            return $this->errorResponse('Image required for media category.', 400);
        }

        if ($category_slug == 'journal' && !$request->link_url && $post->file_url == null) {
            return $this->errorResponse('Link URL required for journal category.', 400);
        }


        if ($category_slug == 'file' && !$request->file('file') && !$post->file_url) {
            return $this->errorResponse('File required for file category.', 400);
        }

        $image_path = $post->image_url;
        if ($request->hasFile('image')) {
            if ($post->image_url && Storage::disk('public')->exists($post->image_url)) {
                Storage::disk('public')->delete($post->image_url);
            }

            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $page_slug = Page::where('id', $request->page_id)->pluck('slug')->first();
            $newImageName = time() . '_' . $page_slug . '.' . $extension;
            $image_path = $image->storeAs('images/posts', $newImageName, 'public');
        }

        $file_path = $post->file_url;
        if ($request->hasFile('file')) {
            if ($post->file_url && Storage::disk('public')->exists($post->file_url)) {
                Storage::disk('public')->delete($post->file_url);
            }
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $page_slug = Page::where('id', $request->page_id)->pluck('slug')->first();
            $newFileName = time() . '_' . $page_slug . '.' . $extension;
            $file_path = $file->storeAs('files/posts', $newFileName, 'public');
        }

        $post->update([
            'title' => $request->title,
            'container' => $request->container,
            'page_id' => $request->page_id,
            'category_id' => $request->category_id,
            'image_url' => $image_path,
            'file_url' => $file_path,
            'link_url' => $request->link_url,
            'status' => $request->status
        ]);

        return $this->successResponse($post, 'Post updated successfully.', 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/posts/{id}",
     *     tags={"Posts"},
     *     summary="Delete a post by ID",
     *     security={{"bearer_token":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the post",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Post data deleted successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Post data deleted successfully."),
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
     *         response=404,
     *         description="Post not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example=false),
     *             @OA\Property(property="message", type="string", example="Post not found."),
     *         )
     *     )
     * )
     */
    public function delete($id)
    {
        $post = Post::find($id);

        if (!$post) {
            return $this->errorResponse('Post not found.', 404);
        }

        // if (!auth()->user()->hasRole('superadmin') && auth()->user()->id !== $post->author_id) {
        //     return $this->errorResponse('You are not authorized to delete this post.', 403);
        // }

        if ($post->image_url) {
            Storage::disk('public')->delete($post->image_url);
        }

        if ($post->file_url) {
            Storage::disk('public')->delete($post->file_url);
        }

        $post->delete();

        return $this->successResponse(null, 'Post data deleted successfully.', 200);
    }

}
