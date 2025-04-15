<?php

// namespace App\Http\Controllers\Admin;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Cache;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Artisan;

// class SystemSettingsController extends Controller
// {
//     /**
//      * Update system settings
//      *
//      * @param  \Illuminate\Http\Request  $request
//      * @return \Illuminate\Http\Response
//      */
//     public function update(Request $request)
//     {
//         $validated = $request->validate([
//             'site_name' => 'sometimes|string|max:255',
//             'maintenance_mode' => 'sometimes|boolean',
//             'default_quiz_visibility' => 'sometimes|boolean',
//             'allow_guest_quizzes' => 'sometimes|boolean',
//             'max_questions_per_quiz' => 'sometimes|integer|min:1|max:100',
//             'max_options_per_question' => 'sometimes|integer|min:2|max:10',
//             'leaderboard_size' => 'sometimes|integer|min:5|max:100',
//             'featured_quizzes_count' => 'sometimes|integer|min:0|max:20',
//         ]);

//         // Store settings in database or cache
//         foreach ($validated as $key => $value) {
//             // Using the DB settings table approach
//             DB::table('settings')->updateOrInsert(
//                 ['key' => $key],
//                 ['value' => $value, 'updated_at' => now()]
//             );

//             // Also update the cache
//             Cache::put("setting.$key", $value, now()->addDay());
//         }

//         // If maintenance mode setting changed, update application state
//         if (isset($validated['maintenance_mode'])) {
//             if ($validated['maintenance_mode']) {
//                 Artisan::call('down');
//             } else {
//                 Artisan::call('up');
//             }
//         }

//         return response()->json([
//             'message' => 'System settings updated successfully',
//             'settings' => $validated
//         ]);
//     }
// }
