<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\HubspotController;

Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
Route::delete('/contacts/{id}', [ContactsController::class, 'destroy'])->name('contacts.destroy');
Route::get('/contacts/export', [ContactsController::class, 'exportContactsToCSV'])->name('contacts.export');
Route::delete('/contacts/{id}', [ContactsController::class, 'destroy'])->name('contacts.destroy');
Route::post('/contacts/delete-multiple', [ContactsController::class, 'deleteMultiple'])->name('contacts.deleteMultiple');
Route::get('/hubspot/contacts', [HubspotController::class, 'index'])->name('hubspot.contacts');