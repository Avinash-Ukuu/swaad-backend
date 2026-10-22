<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    private function rules(): array
    {
        return ['recipient_name' => 'required|string|max:150', 'phone' => 'required|string|regex:/^[6-9][0-9]{9}$/', 'address_line_1' => 'required|string|max:255', 'address_line_2' => 'nullable|string|max:255', 'landmark' => 'nullable|string|max:255', 'city' => 'required|string|max:100', 'state' => 'required|string|max:100', 'postal_code' => 'required|string|regex:/^[1-9][0-9]{5}$/', 'country' => 'sometimes|in:IN', 'is_default' => 'sometimes|boolean'];
    }
    public function index(Request $r)
    {
        return Address::where('user_id', $r->user()->id)->latest()->paginate(20);
    }
    public function store(Request $r)
    {
        $d = $r->validate($this->rules());
        return DB::transaction(function () use ($r, $d) {
            if (($d['is_default'] ?? false)) Address::where('user_id', $r->user()->id)->update(['is_default' => false]);
            return response()->json(Address::create($d + ['user_id' => $r->user()->id, 'country' => $d['country'] ?? 'IN']), 201);
        });
    }
    public function update(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 404);
        $d = $r->validate($this->rules());
        return DB::transaction(function () use ($r, $address, $d) {
            if (($d['is_default'] ?? false)) Address::where('user_id', $r->user()->id)->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update($d);
            return $address->fresh();
        });
    }
    public function destroy(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 404);
        $address->delete();
        return response()->noContent();
    }
}
