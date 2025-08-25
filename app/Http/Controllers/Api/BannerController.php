<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        return Banner::orderBy('position')->get();
    }
public function store(Request $request)
{
    $data = $request->validate([
        'image' => 'required|string',
        'link' => 'nullable|string',
        'position' => 'nullable|integer',
        'status' => 'nullable|integer|in:1,2,3', // Nếu là trạng thái như active, hidden...
    ]);

    $banner = Banner::create($data);

    return response()->json([
        'status' => true,
        'message' => 'Banner created successfully',
        'data' => $banner,
    ], 201);
}


    public function show(Banner $banner)
    {
        return $banner;
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'image' => 'sometimes|required|string ',
            'link' => 'nullable|string',
            'position' => 'nullable|integer',
            'status' => 'boolean',
        ]);

        $banner->update($data);
        return response()->json($banner);
    }

   public function destroy($id)
{
    $banner = Banner::find($id);

    if (!$banner) {
        return response()->json(['message' => 'Banner not found'], 404);
    }

    $banner->delete();

    return response()->json(['message' => 'Deleted successfully']);
}

}
