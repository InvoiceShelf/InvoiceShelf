<?php

namespace InvoiceShelf\Http\Controllers;

use Illuminate\Http\Request;
use InvoiceShelf\Models\Schedule;
use InvoiceShelf\Models\Installer;
use Illuminate\Support\Facades\Auth;


class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {        
        $user = Auth::user();
        $company_id = $user ->companies->first()->id;
        $schedules = Schedule::where('company_id',$company_id)->get();        
        return response()->json($schedules);
    }

    public function getInstallers()
    {        
        $user = Auth::user();
        $company_id = $user ->companies->first()->id;        
        $installers = Installer::where('company_id',$company_id)->get();
        return response()->json($installers);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $company_id = $user ->companies->first()->id;

        $request->validate([
            'title' => 'required|string',            
            'start' => 'required',
        ]);

        $schedule = Schedule::create([
            'title' => $request->title,
            'start' => $request->start,
            'end' => $request->end,
            'description' => $request->description,
            'user_id' => $user->id,
            'company_id' => $company_id,
            'installer_id' => $request->installer_id,
        ]);

        return response()->json($schedule);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Schedule $schedule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Schedule $schedule)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule)
    {
        //
    }
}
