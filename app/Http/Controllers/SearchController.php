<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Blog;
use App\Models\Creator; 
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('query');

        $products = Product::with(['category', 'subCategory', 'variants.images'])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->take(5)
            ->get();

        $creators = Creator::where('name', 'like', "%{$query}%")
            ->take(5)
            ->get();

        $blogs = Blog::with('media')
            ->where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%")
            ->take(5)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $products,
                'creators' => $creators, 
                'blogs' => $blogs
            ]
        ]);
    }
}