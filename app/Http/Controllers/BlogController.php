<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\Media;
use App\Models\Product;
use App\Models\BlogCategory; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'media.*' => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:40960',
            'product_slugs' => 'nullable|array',
            'product_slugs.*' => 'exists:products,slug',
            'category_ids' => 'required|array', 
            'category_ids.*' => 'exists:blog_categories,id', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $blog = Blog::create([
            'title' => $request->title,
            'content' => $request->content,
            'user_id' => auth()->id(),
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $mediaFile) {
                $path = $mediaFile->store('blog_media', 'public');
                $fileType = substr($mediaFile->getMimeType(), 0, 5) == 'image' ? 'image' : 'video';

                Media::create([
                    'blog_id' => $blog->id,
                    'file_path' => $path,
                    'file_type' => $fileType,
                ]);
            }
        }

        if ($request->has('product_slugs')) {
            $products = Product::whereIn('slug', $request->product_slugs)->get();
            $blog->products()->attach($products->pluck('id'));
        }

        $blog->categories()->attach($request->category_ids);

        return response()->json([
            'message' => 'Blog created successfully',
            'blog' => $blog->load(['media', 'products', 'categories']) 
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $blog = Blog::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'media.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi|max:40960',
            'product_slugs' => 'nullable|array',
            'product_slugs.*' => 'exists:products,slug',
            'category_ids' => 'required|array', 
            'category_ids.*' => 'exists:blog_categories,id', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $blog->update([
            'title' => $request->title,
            'content' => $request->content,
        ]);

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $mediaFile) {
                $path = $mediaFile->store('blog_media', 'public');
                $fileType = substr($mediaFile->getMimeType(), 0, 5) == 'image' ? 'image' : 'video';

                Media::create([
                    'blog_id' => $blog->id,
                    'file_path' => $path,
                    'file_type' => $fileType,
                ]);
            }
        }

        if ($request->has('product_slugs')) {
            $products = Product::whereIn('slug', $request->product_slugs)->get();
            $blog->products()->sync($products->pluck('id'));
        }

        $blog->categories()->sync($request->category_ids);

        return response()->json([
            'message' => 'Blog updated successfully',
            'blog' => $blog->load(['media', 'products', 'categories']) 
        ]);
    }

    public function show($id)
    {
        $blog = Blog::with(['media', 'products', 'categories'])->findOrFail($id); 
        return response()->json($blog);
    }
    public function index(Request $request)
    {
        $query = Blog::with(['media', 'products', 'categories']); 

        if ($request->creator) {
            $query->where('title', 'like', "%{$request->creator}%");
        }

        $blogs = $query->latest()->paginate(10);
        return response()->json($blogs);
    }


    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:blog_categories',
            'image' => 'nullable', 
            'image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $data = [
            'name' => $request->name,
        ];
    
        $imagePaths = []; 
    
        if ($request->hasFile('image')) {
            $images = $request->file('image');
    
            if (is_array($images)) {
                foreach ($images as $image) {
                    $imagePath = $image->store('blog_category_images', 'public');
                    $imagePaths[] = $imagePath;
                }
            } else {
                $imagePath = $images->store('blog_category_images', 'public');
                $imagePaths[] = $imagePath;
            }
    
            if (count($imagePaths) > 0) {
                $data['image'] = json_encode($imagePaths); 
            }
        }
         else {
            $data['image'] = null;
        }
    
        $category = BlogCategory::create($data);
    
        return response()->json([
            'message' => 'Blog category created successfully',
            'category' => $category
        ], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = BlogCategory::findOrFail($id);
    
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:blog_categories,name,' . $id,
            'image' => 'nullable',
            'image.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_base64' => 'nullable|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $category->name = $request->input('name');
        
        if ($request->hasFile('image')) {
            if ($category->image) {
                $oldImages = json_decode($category->image, true);
                if (is_array($oldImages)) {
                    foreach ($oldImages as $oldImage) {
                        if (Storage::disk('public')->exists($oldImage)) {
                            Storage::disk('public')->delete($oldImage);
                        }
                    }
                }
            }
            
            $imagePaths = [];
            $images = $request->file('image');
            
            if (is_array($images)) {
                foreach ($images as $image) {
                    $imagePath = $image->store('blog_category_images', 'public');
                    $imagePaths[] = $imagePath;
                }
            } else {
                $imagePath = $images->store('blog_category_images', 'public');
                $imagePaths[] = $imagePath;
            }
            
            $category->image = json_encode($imagePaths);
        }
        else if ($request->has('image_base64')) {
            if ($category->image) {
                $oldImages = json_decode($category->image, true);
                if (is_array($oldImages)) {
                    foreach ($oldImages as $oldImage) {
                        if (Storage::disk('public')->exists($oldImage)) {
                            Storage::disk('public')->delete($oldImage);
                        }
                    }
                }
            }
            
            $imageData = $request->input('image_base64');
            $extension = explode('/', mime_content_type($imageData))[1];
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $imageName = 'blog_category_images/' . uniqid() . '.' . $extension;
            Storage::disk('public')->put($imageName, base64_decode($imageData));
            
            $category->image = json_encode([$imageName]);
        }
    
        $category->save();
    
        return response()->json([
            'message' => 'Blog category updated successfully',
            'category' => $category
        ]);
    }

    public function showCategory($id)
    {
        $category = BlogCategory::findOrFail($id);
        return response()->json($category);
    }

    public function indexCategory()
    {
        $categories = BlogCategory::all();
        return response()->json($categories);
    }

    public function destroyCategory($id)
    {
        $category = BlogCategory::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Blog category deleted successfully']);
    }
}