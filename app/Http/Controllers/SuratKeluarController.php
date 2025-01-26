<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\Lampiran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SuratKeluarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Check if user is Sekdes
     */
    private function isSekdes(): bool
    {
        return User::where('id', Auth::id())
            ->where('username', 'sekdes')
            ->exists();
    }

    /**
     * Get user permissions
     */
    private function checkPermissions(SuratKeluar $suratKeluar = null)
    {
        $isSekdes = $this->isSekdes();

        $permissions = [
            'canCreate' => $isSekdes,
            'canEdit' => $isSekdes,
            'canDelete' => $isSekdes,
            'canView' => true
        ];

        return $permissions;
    }

    /**
     * Display list of surat keluar
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', SuratKeluar::class);

        $user = User::find(Auth::id());
        $isSekdes = $this->isSekdes();

        $perPage = $request->input('per_page', 10);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = SuratKeluar::with(['user', 'lampiran'])
            ->when($search, function ($query, $search) {
                return $query->where('no_surat', 'like', "%{$search}%")
                            ->orWhere('perihal', 'like', "%{$search}%")
                            ->orWhere('pengirim', 'like', "%{$search}%")
                            ->orWhere('penerima', 'like', "%{$search}%");
            })
            ->when($startDate, function ($query, $startDate) {
                return $query->whereDate('tgl_ns', '>=', $startDate);
            })
            ->when($endDate, function ($query, $endDate) {
                return $query->whereDate('tgl_ns', '<=', $endDate);
            })
            ->orderBy('id', 'DESC');

        $suratKeluar = $query->paginate($perPage, ['*'], 'page', $page);

        return Inertia::render('SuratKeluar/Index', [
            'suratKeluar' => $suratKeluar,
            'permissions' => $this->checkPermissions(),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'per_page' => $perPage,
                'search' => $search,
                'page' => $page
            ],
        ]);
}

    /**
     * Store surat keluar
     */
    public function store(Request $request)
    {
        if (!$this->isSekdes()) {
            abort(403, 'Hanya Sekdes yang dapat membuat surat keluar.');
        }

        $request->validate([
            'no_surat' => 'required',
            'tgl_ns' => 'required|date',
            'penerima' => 'required|string',
            'pengirim' => 'required|string',
            'perihal' => 'required',
            'lampiran' => 'required|file|max:10240'
        ]);

        $token = Str::random(40);

        // Create surat keluar
        $suratKeluar = SuratKeluar::create([
            'no_surat' => $request->no_surat,
            'tgl_ns' => $request->tgl_ns,
            'pengirim' => $request->pengirim,
            'penerima' => $request->penerima,
            'perihal' => $request->perihal,
            'token_lampiran' => $token,
            'user_id' => Auth::id(),
            'tgl_sk' => now()->format('d-m-Y')
        ]);

        // Handle lampiran
        if ($request->hasFile('lampiran')) {
            $file = $request->file('lampiran');
            $fileName = $file->getClientOriginalName();

            $file->storeAs('lampiran', $fileName);
            Lampiran::create([
                'nama_berkas' => $fileName,
                'ukuran' => $file->getSize(),
                'token_lampiran' => $token
            ]);
        }

        return redirect()->route('surat-keluar.index')
            ->with('message', 'Surat keluar berhasil ditambahkan');
    }

    /**
     * Show surat keluar
     */
    public function show(SuratKeluar $suratKeluar)
    {
        $this->authorize('view', $suratKeluar);

        return Inertia::render('SuratKeluar/Show', [
            'suratKeluar' => $suratKeluar->load(['user', 'lampiran']),
            'canEdit' => $this->isSekdes()
        ]);
    }

    /**
     * Show the form for editing an incoming letter.
     */
    public function edit(SuratKeluar $suratKeluar)
    {
        $this->authorize('update', $suratKeluar);

        $users = User::orderBy('nama_lengkap')->get();

        return Inertia::render('SuratMasuk/Edit', [
            'suratKeluar' => $suratKeluar->load(['user', 'lampiran']),
            'users' => $users
        ]);
    }

    /**
     * Update surat keluar
     */
    public function update(Request $request, SuratKeluar $suratKeluar)
    {
        if (!$this->isSekdes()) {
            abort(403, 'Hanya Sekdes yang dapat mengedit surat keluar.');
        }

        $request->validate([
            'tgl_ns' => 'required|date',
            'penerima' => 'required|string',
            'pengirim' => 'required|string',
            'perihal' => 'required',
        ]);

        $suratKeluar->update([
            'tgl_ns' => $request->tgl_ns,
            'penerima' => $request->penerima,
            'pengirim' => $request->pengirim,
            'perihal' => $request->perihal
        ]);

        return redirect()->route('surat-keluar.index')
            ->with('message', 'Surat keluar berhasil diupdate');
    }

    /**
     * Delete surat keluar
     */
    public function destroy(SuratKeluar $suratKeluar)
    {
        $this->authorize('delete', $suratKeluar);

        if ($suratKeluar->token_lampiran) {
            $lampiran = Lampiran::where('token_lampiran', $suratKeluar->token_lampiran)->get();

            foreach ($lampiran as $file) {
                Storage::delete('lampiran/' . $file->nama_berkas);
                $file->delete();
            }
        }

        $suratKeluar->delete();

        return redirect()->route('surat-keluar.index')
            ->with('message', 'Surat keluar berhasil dihapus');
    }

    /**
     * Download lampiran
     */
    public function downloadLampiran(SuratKeluar $suratKeluar)
    {
        $this->authorize('downloadLampiran', $suratKeluar);

        $lampiran = Lampiran::where('token_lampiran', $suratKeluar->token_lampiran)->first();

        if (!$lampiran) {
            return redirect()->back()
                ->with('error', 'Lampiran tidak ditemukan');
        }

        $path = storage_path('app/lampiran/' . $lampiran->nama_berkas);

        if (!file_exists($path)) {
            return redirect()->back()
                ->with('error', 'File tidak ditemukan');
        }

        return response()->download($path, $lampiran->nama_berkas);
    }
}
