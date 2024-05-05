<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvoiceShelf\Models\Note;
use InvoiceShelf\Models\User;

class AddCompanyToNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->unsignedInteger('company_id')->nullable();
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        $user = User::where('role', 'super admin')->first();

        if ($user) {
            $notes = Note::where('company_id', null)->get();
            $notes->map(function ($note) use ($user) {
                $note->company_id = $user->companies()->first()->id;
                $note->save();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notes', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['company_id']);
            }
        });
    }
}
