<?php

namespace App\Http\Controllers;

use App\Models\AdminProfile;
use App\Models\DataKkl;
use App\Models\DataKkn;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'admin');
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = User::with('profilable');

        if ($search) {
            $searchWildcard = '%'.$search.'%';
            $query->where(function ($q) use ($searchWildcard) {
                $q->where('name', 'like', $searchWildcard)
                    ->orWhere('email', 'like', $searchWildcard)
                    ->orWhereHas('profilable', function ($q) use ($searchWildcard) {
                        $q->where('nim', 'like', $searchWildcard)
                            ->orWhere('nip', 'like', $searchWildcard);
                    });
            });
        }

        switch ($tab) {
            case 'admin':
                $query->whereIn('role', ['admin', 'superadmin']);
                $users = $query->latest()->paginate($perPage)->withQueryString();

                return Inertia::render('Admin/User/UserPage', [
                    'tab' => $tab,
                    'users' => $users,
                    'filters' => $request->only(['search']),
                ]);

            case 'dosen':
                $query->where('role', 'dosen');
                $dosens = $query->latest()->paginate($perPage)->withQueryString();

                return Inertia::render('Admin/User/UserPage', [
                    'tab' => $tab,
                    'dosens' => $dosens,
                    'filters' => $request->only(['search']),
                ]);

            case 'mahasiswa':
                $query->where('role', 'mahasiswa');
                $mahasiswas = $query->latest()->paginate($perPage)->withQueryString();

                return Inertia::render('Admin/User/UserPage', [
                    'tab' => $tab,
                    'mahasiswas' => $mahasiswas,
                    'filters' => $request->only(['search']),
                ]);

            case 'all':
                $allUsers = $query->latest()->paginate($perPage)->withQueryString();

                return Inertia::render('Admin/User/UserPage', [
                    'tab' => $tab,
                    'allUsers' => $allUsers,
                    'filters' => $request->only(['search']),
                ]);

            default:
                $query->whereIn('role', ['admin', 'superadmin']);
                $users = $query->latest()->paginate($perPage)->withQueryString();

                return Inertia::render('Admin/User/UserPage', [
                    'tab' => 'admin',
                    'users' => $users,
                    'filters' => $request->only(['search']),
                ]);
        }
    }

    private function getValidationRules($userType, $id = null)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $id ? Rule::unique('users')->ignore($id) : 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ];

        // Add password rule only for new users
        if (! $id) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        }

        // Add role-specific rules
        switch ($userType) {
            case 'admin':
                $rules['role'] = ['required', Rule::in(['admin', 'superadmin'])];
                break;
            case 'dosen':
                $rules['nip'] = ['required', 'string', $id ? Rule::unique('users')->ignore($id) : 'unique:users'];
                break;
            case 'mahasiswa':
                $rules['nim'] = ['required', 'string', $id ? Rule::unique('users')->ignore($id) : 'unique:users'];
                break;
        }

        return $rules;
    }

    private function getUserTypeLabel($tab)
    {
        $labels = [
            'admin' => 'Admin',
            'superadmin' => 'Super Admin',
            'dosen' => 'Dosen',
            'mahasiswa' => 'Mahasiswa',
            'all' => 'User',
        ];

        return $labels[$tab] ?? 'User';
    }

    public function store(Request $request)
    {
        if (Auth::user()->role !== 'superadmin') {
            return redirect()->back()->with('flash', [
                'message' => 'Unauthorized access',
                'type' => 'error',
            ]);
        }

        $tab = $request->query('tab', 'user');
        $rules = $this->getValidationRules($tab);

        try {
            $validated = $request->validate($rules);

            DB::transaction(function () use ($validated, $tab) {
                $profileData = array_filter([
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);

                if (isset($validated['nim'])) {
                    $profileData['nim'] = $validated['nim'];
                }
                if (isset($validated['nip'])) {
                    $profileData['nip'] = $validated['nip'];
                }

                $profile = match ($tab) {
                    'mahasiswa' => MahasiswaProfile::create($profileData),
                    'dosen' => DosenProfile::create($profileData),
                    default => AdminProfile::create($profileData),
                };

                $user = new User([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => match ($tab) {
                        'admin' => $validated['role'] ?? 'admin',
                        'dosen' => 'dosen',
                        'mahasiswa' => 'mahasiswa',
                        default => 'admin',
                    },
                ]);

                $profile->user()->save($user);

                if ($tab === 'mahasiswa') {
                    DataKkl::create([
                        'user_id' => $user->id,
                        'dosen_id' => null,
                        'tanggal_mulai' => now(),
                        'tanggal_selesai' => now()->addMonths(6),
                    ]);

                    DataKkn::create([
                        'user_id' => $user->id,
                        'dosen_id' => null,
                        'tanggal_mulai' => now(),
                        'tanggal_selesai' => now()->addMonths(6),
                    ]);
                }
            });

            return redirect()->back()->with('flash', [
                'message' => ucfirst($tab).' berhasil ditambahkan',
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('flash', [
                'message' => 'Gagal menambahkan '.$this->getUserTypeLabel($tab),
                'type' => 'error',
            ]);
        }
    }

    public function update(Request $request, string $id)
    {
        if (Auth::user()->role !== 'superadmin') {
            return redirect()->back()->with('flash', [
                'message' => 'Unauthorized access',
                'type' => 'error',
            ]);
        }

        $tab = $request->query('tab', 'user');

        try {
            $user = User::with('profilable')->findOrFail($id);
            $rules = $this->getValidationRules($tab, $id);
            $validated = $request->validate($rules);

            DB::transaction(function () use ($user, $validated) {
                if (isset($validated['password'])) {
                    $validated['password'] = Hash::make($validated['password']);
                } else {
                    unset($validated['password']);
                }

                $userFields = array_intersect_key($validated, array_flip(['name', 'email', 'password', 'role']));
                $user->update($userFields);

                $profileFields = array_filter([
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'nim' => $validated['nim'] ?? null,
                    'nip' => $validated['nip'] ?? null,
                ]);

                if ($user->profilable) {
                    $user->profilable->update($profileFields);
                }
            });

            return redirect()->back()->with('flash', [
                'message' => ucfirst($tab).' berhasil diperbarui',
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('flash', [
                'message' => 'Gagal memperbarui '.$this->getUserTypeLabel($tab),
                'type' => 'error',
            ]);
        }
    }

    public function destroy(Request $request, string $id)
    {
        if (Auth::user()->role !== 'superadmin') {
            return redirect()->back()->with('flash', [
                'message' => 'Unauthorized access',
                'type' => 'error',
            ]);
        }

        $tab = $request->query('tab', 'user');

        try {
            $user = User::findOrFail($id);

            // Delete associated KKL and KKN data if user is mahasiswa
            if ($user->role === 'mahasiswa') {
                DataKkl::where('user_id', $id)->delete();
                DataKkn::where('user_id', $id)->delete();
            }

            $user->delete();

            return redirect()->back()->with('flash', [
                'message' => ucfirst($tab).' berhasil dihapus',
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('flash', [
                'message' => 'Gagal menghapus '.$this->getUserTypeLabel($tab),
                'type' => 'error',
            ]);
        }
    }
}
