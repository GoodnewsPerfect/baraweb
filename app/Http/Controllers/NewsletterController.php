<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\Request;
use Validator;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $newsletter = Newsletter::updateOrCreate(
            ['email' => $request->email],
            ['is_subscribed' => true]
        );
    
        $message = $newsletter->wasRecentlyCreated
            ? 'Successfully subscribed to newsletter'
            : 'Successfully resubscribed to newsletter';
    
        return response()->json(['message' => $message, 'data' => $newsletter], 201);
    }
    

    public function unsubscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $newsletter = Newsletter::where('email', $request->email)->first();

        if (!$newsletter) {
            return response()->json(['message' => 'Email not found in newsletter list'], 404);
        }

        $newsletter->update(['is_subscribed' => false]);

        return response()->json(['message' => 'Successfully unsubscribed from newsletter'], 200);
    }

    public function index()
    {
        $subscribers = Newsletter::where('is_subscribed', true)->get();
        return response()->json(['data' => $subscribers, 'count' => $subscribers->count()], 200);
    }
}