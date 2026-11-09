<?php

namespace App\Http\Controllers;

use App\Models\AdoptionApplication;
use App\Models\ApiToken;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    public function overview()
    {
        return response()->json(['data' => [
            'available_pets' => Pet::where('status', 'available')->count(),
            'pending_applications' => AdoptionApplication::whereIn('status', ['submitted', 'reviewing'])->count(),
            'adopted_pets' => Pet::where('status', 'adopted')->count(),
            'open_messages' => DB::table('contact_messages')->whereNull('resolved_at')->count(),
        ]]);
    }

    public function applications(Request $request)
    {
        $fields = $request->validate(['status' => 'nullable|in:submitted,reviewing,approved,rejected', 'search' => 'nullable|string|max:100']);
        $query = AdoptionApplication::with(['pet', 'user:id,name,email'])->latest();
        if (!empty($fields['status'])) { $query->where('status', $fields['status']); }
        if (!empty($fields['search'])) {
            $search = '%'.$fields['search'].'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $search)->orWhereHas('pet', fn ($p) => $p->where('name', 'like', $search))->orWhereHas('user', fn ($u) => $u->where('name', 'like', $search)));
        }
        return response()->json($query->paginate(20));
    }

    public function review(Request $request, AdoptionApplication $application)
    {
        $data = $request->validate(['status' => 'required|in:reviewing,approved,rejected', 'review_note' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($request, $application, $data) {
            // Always lock the pet first so competing approvals use the same lock order.
            $pet = Pet::whereKey($application->pet_id)->lockForUpdate()->firstOrFail();
            $record = AdoptionApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['submitted', 'reviewing'], true), 409, 'This application has already been decided.');
            if ($data['status'] === 'approved') {
                abort_if($pet->status === 'adopted', 409, 'This pet has already been adopted.');
                $pet->update(['status' => 'adopted']);
                AdoptionApplication::where('pet_id', $pet->id)->where('id', '!=', $record->id)->whereIn('status', ['submitted', 'reviewing'])->update([
                    'status' => 'rejected', 'review_note' => 'Another application for this pet was approved.',
                    'reviewed_at' => now(), 'reviewed_by' => $request->user()->id,
                ]);
            }
            $record->forceFill([...$data, 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();
            return response()->json(['data' => $record->load(['pet', 'user:id,name,email'])]);
        });
    }

    public function savePet(Request $request, ?Pet $pet = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80', 'species' => 'required|in:Dogs,Cats,Rabbits,Birds,Small pets',
            'breed' => 'required|string|max:100', 'age_months' => 'required|integer|min:1|max:600',
            'gender' => 'required|in:Male,Female', 'size' => 'required|in:Small,Medium,Large',
            'location' => 'required|string|max:120', 'description' => 'required|string|min:20|max:4000',
            'traits' => 'present|array|max:8', 'traits.*' => 'required|string|max:40',
            'image' => ['required', 'string', 'regex:~^/images/[a-z0-9-]+\.(jpg|png|webp)$~'],
            'adoption_fee' => 'required|integer|min:0|max:100000', 'status' => 'required|in:available,on_hold,adopted',
        ]);
        $created = !$pet;
        return DB::transaction(function () use ($pet, $data, $created) {
            if ($pet) {
                $pet = Pet::whereKey($pet->id)->lockForUpdate()->firstOrFail();
                abort_if($data['status'] !== 'adopted' && AdoptionApplication::where('pet_id', $pet->id)->where('status', 'approved')->exists(), 409, 'A pet with an approved adoption must remain adopted.');
                $pet->update($data);
            } else {
                $pet = Pet::create([...$data, 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(8))]);
            }
            return response()->json(['data' => $pet], $created ? 201 : 200);
        });
    }

    public function messages(Request $request)
    {
        return response()->json(DB::table('contact_messages')->orderByDesc('id')->paginate(20));
    }

    public function resolveMessage(Request $request, int $message)
    {
        $data = $request->validate(['resolved' => 'required|boolean']);
        abort_unless(DB::table('contact_messages')->where('id', $message)->exists(), 404);
        DB::table('contact_messages')->where('id', $message)->update(['resolved_at' => $data['resolved'] ? now() : null, 'updated_at' => now()]);
        return response()->noContent();
    }

    public function users(Request $request)
    {
        $fields = $request->validate(['search' => 'nullable|string|max:100']);
        $query = User::select(['id', 'name', 'email', 'role', 'created_at'])->orderBy('name');
        if (!empty($fields['search'])) {
            $search = '%'.$fields['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }
        return response()->json($query->paginate(20));
    }

    public function changeRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|in:adopter,manager,admin']);
        abort_if($request->user()->id === $user->id, 409, 'You cannot change your own role.');
        return DB::transaction(function () use ($request, $user, $data) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->contains('id', $request->user()->id), 403, 'Your admin access has changed. Please sign in again.');
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($account->role === 'admin' && $data['role'] !== 'admin' && $admins->count() <= 1, 409, 'Keep at least one admin account.');
            $account->forceFill($data)->save();
            ApiToken::where('user_id', $account->id)->delete();
            return response()->json(['data' => $account]);
        });
    }
}
