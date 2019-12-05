<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', "ArticlesController@index");

Auth::routes();

Route::get('/home', 'ArticlesController@index')->name('home');

Route::resource('articles', 'ArticlesController');
Route::resource('categories', 'CategoriesController');
Route::resource('profiles', 'ProfilesController');
Route::resource('comments', 'CommentsController');