<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AnnoucementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view announcement');
        try {
            $announcements = Announcement::get();
            return view('dashboard.announcements.index',compact('announcements'));
        } catch (\Throwable $th) {
            Log::error('Announcement Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create announcement');
        try {
            return view('dashboard.announcements.create');
        } catch (\Throwable $th) {
            Log::error('Announcement Create Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create announcement');
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $announcement = new Announcement();
            $announcement->title = $request->title;
            $announcement->description = $request->description;
            $announcement->save();

            DB::commit();
            return redirect()->route('dashboard.announcements.index')->with('success', 'Announcement Created Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Announcement Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('update announcement');
        try {
            $annoucement = Announcement::findOrFail($id);
            return view('dashboard.announcements.edit', compact('annoucement'));
        } catch (\Throwable $th) {
            Log::error('announcement Edit Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $this->authorize('update announcement');
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput($request->all())->with('error', 'Validation Error!');
        }

        try {
            DB::beginTransaction();
            $announcement = Announcement::findOrFail($id);
            $announcement->title = $request->title;
            $announcement->description = $request->description;
            $announcement->save();

            DB::commit();
            return redirect()->route('dashboard.announcements.index')->with('success', 'Announcement Updated Successfully');
        } catch (\Throwable $th) {
            throw $th;
            DB::rollBack();
            Log::error('Announcement Created Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete announcement');
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();
            return redirect()->back()->with('success', 'Announcement Deleted Successfully!');
        } catch (\Throwable $th) {
            Log::error('Announcement Delete Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(string $id)
    {
        $this->authorize('update announcement');
        try {
            $announcement = Announcement::findOrFail($id);
            $message = $announcement->is_active == 'active' ? 'Announcement Deactivated Successfully' : 'Announcement Activated Successfully';
            if ($announcement->is_active == 'active') {
                $announcement->is_active = 'inactive';
                $announcement->save();
            } else {
                $announcement->is_active = 'active';
                $announcement->save();
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $th) {
            Log::error('Announcement Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}
