<?php

use Illuminate\Support\Facades\Route;

Route::group([
	'prefix'     => HungLv\Telescope\Telescope::config('path', 'telescope'),
	'middleware' => ['telescope'],
	'namespace'  => 'HungLv\Telescope\Http\Controllers',
], function()
{
	Route::get('/', ['as' => 'telescope.index', 'uses' => 'TelescopeController@index']);
	Route::get('insights', ['as' => 'telescope.insights', 'uses' => 'TelescopeController@insights']);
	Route::get('batch/{batchId}', ['as' => 'telescope.batch', 'uses' => 'TelescopeController@batch']);
	Route::post('clear', ['as' => 'telescope.clear', 'uses' => 'TelescopeController@clear']);
	Route::get('{type}', ['as' => 'telescope.entries', 'uses' => 'TelescopeController@entries']);
	Route::get('{type}/{uuid}', ['as' => 'telescope.show', 'uses' => 'TelescopeController@show']);
});
