<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContactsController;
use App\Http\Controllers\HubspotController;

Route::get('/contacts', [
    ContactsController::class, 'index'
])->name('contacts.index');
Route::delete('/contacts/{id}', [
    ContactsController::class, 'destroy'
])->name('contacts.destroy');
Route::get('/contacts/export', [
    ContactsController::class, 'exportContactsToCSV'])->name('contacts.export');
Route::delete('/contacts/{id}', [
    ContactsController::class, 'destroy'
])->name('contacts.destroy');
Route::post('/contacts/delete-multiple', [
    ContactsController::class, 'deleteMultiple'
])->name('contacts.deleteMultiple');
Route::get('/hubspot/contacts', [
    HubspotController::class, 'index'
])->name('hubspot.contacts');
Route::get('/hubspot/search/contacts', [
    HubspotController::class, 'search'
])->name('hubspot.search.contacts');
Route::post('/contacts/{id}/mark-delete', [
    ContactsController::class, 'markDelete'
])->name('contacts.markDelete');
Route::get('/hubspot/contacts/export', [
    HubspotController::class, 'exportCSV'
])->name('hubspot.contacts.export');
Route::get('/hubspot/contacts/display', [HubspotController::class, 'displayContacts'])->name('hubspot.contacts.display');


Route::prefix('hubspot')->group(function () {
    Route::get('/', [HubspotController::class, 'dashboard'])->name('hubspot.dashboard');
    Route::get('/history', [HubspotController::class, 'syncHistory'])->name('hubspot.history');
    Route::post('/sync', [HubspotController::class, 'startSync'])->name('hubspot.sync');
    Route::get('/status', [HubspotController::class, 'checkStatus'])->name('hubspot.status');
    Route::post('/cancel', [HubspotController::class, 'cancelSync'])->name('hubspot.cancel');
    Route::get('/retrieval-history', [HubspotController::class, 'retrievalHistory'])->name('hubspot.retrieval-history');

});