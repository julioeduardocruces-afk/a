<?php

namespace App\Http\Controllers;

class LandingController extends Controller
{
    public function home()
    {
        return view('landing.home');
    }

    public function howItWorks()
    {
        return view('landing.how-it-works');
    }

    public function faq()
    {
        return view('landing.faq');
    }
}
