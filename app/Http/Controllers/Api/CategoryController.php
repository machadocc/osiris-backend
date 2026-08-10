<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return CategoryResource::collection(
            $request->user()->categories()->orderBy('name')->get()
        );
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $request->user()->categories()->create($request->validated());

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->authorizeOwnership($request, $category);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->authorizeOwnership($request, $category);

        $category->delete();

        return response()->json(status: 204);
    }

    private function authorizeOwnership(Request $request, Category $category): void
    {
        abort_unless($category->user_id === $request->user()->id, 403);
    }
}
