<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return AccountResource::collection(
            $request->user()->accounts()->orderBy('name')->get()
        );
    }

    public function store(StoreAccountRequest $request)
    {
        $account = $request->user()->accounts()->create($request->validated());

        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $this->authorizeOwnership($request, $account);

        $account->update($request->validated());

        return new AccountResource($account);
    }

    public function destroy(Request $request, Account $account)
    {
        $this->authorizeOwnership($request, $account);

        $account->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Account $account): void
    {
        abort_unless($account->user_id === $request->user()->id, 403);
    }
}
