<?php

namespace App\Http\Controllers;

use App\Models\AdoptionApplication;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PetController extends Controller
{
    public function index(Request $request)
    {
        $query = Pet::query()->orderByDesc('id');
        if ($request->filled('species')) { $query->where('species', $request->string('species')); }
        return response()->json(['data' => $query->get()]);
    }

    public function favorites(Request $request)
    {
        return response()->json(['data' => DB::table('favorites')->where('user_id', $request->user()->id)->pluck('pet_id')]);
    }

    public function saveFavorite(Request $request, Pet $pet)
    {
        DB::table('favorites')->insertOrIgnore(['user_id' => $request->user()->id, 'pet_id' => $pet->id]);
        return response()->json(['saved' => true]);
    }

    public function removeFavorite(Request $request, Pet $pet)
    {
        DB::table('favorites')->where('user_id', $request->user()->id)->where('pet_id', $pet->id)->delete();
        return response()->noContent();
    }

    public function applications(Request $request)
    {
        return response()->json(['data' => AdoptionApplication::with('pet')->where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function apply(Request $request, Pet $pet)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[+0-9()\s-]+$/'],
            'city' => ['required', 'string', 'max:120'],
            'housing' => ['required', 'in:House,Apartment,Condominium,Other'],
            'experience' => ['required', 'in:First-time adopter,Have pets now,Had pets before'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        return DB::transaction(function () use ($request, $pet, $data) {
            $lockedPet = Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedPet->status !== 'available', 409, 'This pet is no longer accepting applications.');
            abort_if(AdoptionApplication::where('user_id', $request->user()->id)->where('pet_id', $pet->id)->exists(), 409, 'You have already applied for this pet. View My applications to follow your request.');
            $application = AdoptionApplication::create([
                ...$data, 'user_id' => $request->user()->id, 'pet_id' => $pet->id,
                'reference' => 'PAW-'.Str::upper(Str::random(8)), 'status' => 'submitted',
            ]);
            return response()->json(['data' => $application->load('pet')], 201);
        });
    }

    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $id = DB::table('contact_messages')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Your message has been saved.', 'reference' => 'MSG-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)], 201);
    }
}
