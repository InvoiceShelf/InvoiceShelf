<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CheckDuplicateReceiptsController extends Controller
{
    public function __invoke(Request $request, FileAdder $fileAdder)
    {
        $request->validate([
            'file_names' => 'required|array',
            'file_names.*' => 'string',
        ]);

        $fileNames = $request->input('file_names');
        $companyId = $request->header('company');

        $map = [];
        foreach ($fileNames as $fileName) {
            $sanitized = $fileAdder->defaultSanitizer($fileName);
            $map[$sanitized][] = $fileName;
            // Also map the original filename in case it wasn't sanitized in DB
            if ($sanitized !== $fileName) {
                $map[$fileName][] = $fileName;
            }
        }

        $checkNames = array_keys($map);

        // Find media items that match the file names and belong to Expenses of the current company
        $foundFiles = Media::whereIn('file_name', $checkNames)
            ->where('model_type', Expense::class)
            ->whereHasMorph('model', [Expense::class], function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->pluck('file_name')
            ->unique()
            ->values()
            ->toArray();

        $duplicates = [];
        foreach ($foundFiles as $foundFile) {
            if (isset($map[$foundFile])) {
                foreach ($map[$foundFile] as $originalName) {
                    $duplicates[] = $originalName;
                }
            }
        }

        return response()->json([
            'duplicates' => array_values(array_unique($duplicates)),
        ]);
    }
}
