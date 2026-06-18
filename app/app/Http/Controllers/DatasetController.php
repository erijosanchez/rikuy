<?php

namespace App\Http\Controllers;

use App\Ingesta\CanonicalSchema;
use App\Ingesta\SpreadsheetReader;
use App\Jobs\ProcessDataset;
use App\Models\Dataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DatasetController extends Controller
{
    public function __construct(protected SpreadsheetReader $reader) {}

    /**
     * Recibe el archivo, lo guarda y crea el dataset en estado "mapping".
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('datasets', 'local');

        $name = $validated['name']
            ?? Str::of($file->getClientOriginalName())->beforeLast('.')->toString();

        $dataset = Dataset::create([
            'name' => $name ?: 'Dataset sin nombre',
            'source' => 'upload',
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => Dataset::STATUS_MAPPING,
            'rows' => 0,
        ]);

        return redirect()->route('datasets.map', $dataset);
    }

    /**
     * Paso de mapeo: muestra columnas detectadas y campos canónicos.
     */
    public function map(Dataset $dataset): Response
    {
        abort_unless($dataset->file_path && Storage::disk('local')->exists($dataset->file_path), 404);

        $absolute = Storage::disk('local')->path($dataset->file_path);
        $headers = $this->reader->headers($absolute);

        $sample = [];
        $this->reader->eachRow($absolute, function (array $row, int $n) use (&$sample) {
            if ($n <= 3) {
                $sample[] = $row;
            }
        });

        return Inertia::render('Datasets/Map', [
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
                'original_filename' => $dataset->original_filename,
                'status' => $dataset->status,
            ],
            'headers' => $headers,
            'canonicalFields' => CanonicalSchema::fields(),
            'suggested' => $this->suggestMapping($headers),
            'sample' => $sample,
        ]);
    }

    /**
     * Guarda el mapeo, marca el dataset para procesar y lo encola.
     */
    public function process(Request $request, Dataset $dataset): RedirectResponse
    {
        $absolute = Storage::disk('local')->path($dataset->file_path);
        $headers = $this->reader->headers($absolute);

        $input = (array) $request->input('map', []);
        $map = [];
        foreach (CanonicalSchema::keys() as $key) {
            $header = $input[$key] ?? null;
            if ($header !== null && $header !== '' && in_array($header, $headers, true)) {
                $map[$key] = $header;
            }
        }

        $missing = array_diff(CanonicalSchema::requiredKeys(), array_keys($map));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'map' => 'Faltan campos obligatorios por mapear: '.implode(', ', $missing).'.',
            ]);
        }

        $dataset->forceFill([
            'column_map' => $map,
            'status' => Dataset::STATUS_PROCESSING,
            'error' => null,
        ])->save();

        ProcessDataset::dispatch($dataset->id);

        return redirect()->route('dashboard')
            ->with('status', "Procesando «{$dataset->name}»…");
    }

    /**
     * Heurística simple: si el nombre del header coincide con el campo, lo sugiere.
     */
    protected function suggestMapping(array $headers): array
    {
        $suggested = [];
        foreach (CanonicalSchema::keys() as $key) {
            foreach ($headers as $header) {
                if (Str::contains(Str::lower(Str::ascii($header)), $key)) {
                    $suggested[$key] = $header;
                    break;
                }
            }
        }

        return $suggested;
    }
}
