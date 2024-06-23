<?php

namespace InvoiceShelf\Http\Controllers;

use Illuminate\Http\Request;
use InvoiceShelf\Models\Schedule;
use InvoiceShelf\Models\Installer;
use InvoiceShelf\Models\Customer;
use Illuminate\Support\Facades\Auth;


class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    protected $user;
    protected $company_id;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            if ($this->user) {
                $this->company_id = $this->user->companies->first()->id;
            }
            return $next($request);
        });
    }

    public function index()
    {
        $schedules = Schedule::where('company_id', $this->company_id)
            ->with('customer')
            ->with('installer')
            ->get();

        return response()->json($schedules);
    }

    public function getInstallers()
    {
        $installers = Installer::where('company_id', $this->company_id)->get();
        return response()->json($installers);
    }

    public function getCustomers()
    {
        $customers = Customer::where('company_id', $this->company_id)->get();
        return response()->json($customers);
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
        $request->validate(
            [
                'title' => 'required',
                'start' => 'required',
                'customer_id' => 'required',
                'installer_id' => 'required',
            ],
            [
                'title.required' => 'The title is required.',
                'start.required' => 'The start date is required.',
                'customer_id.required' => 'The customer is required.',
                'installer_id.required' => 'The installer is required.',
            ]
        );

        $schedule = Schedule::create([
            'title' => $request->title,
            'start' => $request->start,
            'end' => $request->end,
            'description' => $request->description,
            'user_id' => $this->user->id,
            'company_id' => $this->company_id,
            'customer_id' => $request->customer_id,
            'installer_id' => $request->installer_id,
            'color' => $request->color,
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
    public function update(Request $request, $id)
    {
        $request->validate(
            [
                'title' => 'required',
                'start' => 'required',
                'customer_id' => 'required',
                'installer_id' => 'required',
            ],
            [
                'title.required' => 'The title is required.',
                'start.required' => 'The start date is required.',
                'customer_id.required' => 'The customer is required.',
                'installer_id.required' => 'The installer is required.',
            ]
        );

        $schedule = Schedule::findOrFail($id);

        $schedule->update([
            'title' => $request->title,
            'start' => $request->start,
            'end' => $request->end,
            'description' => $request->description,
            'user_id' => $this->user->id,
            'company_id' => $this->company_id,
            'customer_id' => $request->customer_id,
            'installer_id' => $request->installer_id,
            'color' => $request->color,
        ]);

        return response()->json($schedule);
    }

    public function dragdrop(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $schedule->update([
            'start' => $request->start,
            'end' => $request->end,
        ]);

        return response()->json($schedule);
    }

    public function resize(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $schedule->update([
            'end' => $request->end,
        ]);

        return response()->json($schedule);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json($schedule);
    }

    public function getToken()
    {
        $csrf_token = csrf_token();
        return response()->json($csrf_token);
    }
}
