<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PushSubscription\StorePushSubscriptionRequest;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request)
    {
        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $request->input('endpoint')],
            [
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
            ],
        );

        return response()->json(status: 201);
    }

    public function destroy(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->pushSubscriptions()
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        return response()->json(status: 204);
    }
}
