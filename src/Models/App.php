<?php

namespace Hwkdo\IntranetAppBase\Models;

use Illuminate\Database\Eloquent\Model;

class App extends Model
{
    protected $fillable = ['name', 'description', 'url', 'icon', 'color'];
}