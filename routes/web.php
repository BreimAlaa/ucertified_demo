<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


Route::view('/', 'welcome');

Route::post('/', function (Request $request) {

    $request->validate([
        'certification_issuer' => 'required|string|max:255',
        'recipient_name' => 'required|string|max:255',
        'certification_name' => 'required|string|max:255',
        'certification_file' => 'required|mimes:pdf|max:2048',
    ]);

    $content = $request->file('certification_file')->get();
    $base64_file = base64_encode($content);
    $hash = hash('sha256', $base64_file);

    $details = json_encode($request->only('certification_issuer', 'recipient_name', 'certification_name'));
    $base64_details = base64_encode($details);

    $comment = '#@#' . $hash . '#@#' . $base64_details;

    $modifiedPDFContent = $content . '\n' . '% ' . $comment;

    $modifiedFileName = Str::random(40) . '.pdf';

    Storage::put($modifiedFileName, $modifiedPDFContent);
    return response()
        ->file(storage_path('app/' . $modifiedFileName))
        ->deleteFileAfterSend();


});

Route::view('/verify', 'verify');

Route::post('/verify', function (Request $request) {
    $request->validate([
        'certification_file' => 'required|mimes:pdf|max:2048',
    ]);

    try {
        $content = $request->file('certification_file')->get();

        $file = explode('\\n% #@#', $content);
        $base64_file = base64_encode($file[0]);

        $hash = hash('sha256', $base64_file);

        $provided_hash = explode('#@#', $file[1])[0];
        $provided_details = explode('#@#', $file[1])[1];
        $details = json_decode(base64_decode($provided_details), true);

        $details['verified'] = $hash == $provided_hash ? 'Yes' : 'No';

    } catch (Exception $e) {
        $details = ['Verified' => 'No'];
    }

    $to_camel_case = function ($string) {
        $string = str_replace('_', ' ', $string);
        return ucwords($string);
    };
    $details = array_combine(
        array_map($to_camel_case, array_keys($details)),
        $details
    );

    return view('verify-results', compact('details'));
});
