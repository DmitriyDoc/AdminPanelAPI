<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Collection;
use App\Models\LocalizingFranchise;
use App\Models\CollectionsCategoriesPivot;
use App\Models\CollectionsFranchisesPivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Lang;
use App\Models\MovieInfo;
use Illuminate\Support\Facades\Storage;

class CategoriesManagementController extends Controller
{
    private function getFranchiseLabelField(): string
    {
        $locale = Lang::locale();
        return $locale === 'ru' ? 'label_ru' : 'label_en';
    }

    private function getCollectionLabelField(): string
    {
        return 'label_' . Lang::locale();
    }
    /**
     * Сбрасывает published с 2 на 1 для всех movies указанных коллекций.
     *
     * @param array|int $collectionIds
     */
    private function resetMoviesPublished($collectionIds): void
    {
        $ids = is_array($collectionIds) ? $collectionIds : [$collectionIds];
        if (empty($ids)) return;

        MovieInfo::whereIn(
            'id_movie',
            CollectionsCategoriesPivot::whereIn('collection_id', $ids)
                ->distinct()
                ->pluck('id_movie')
        )
            ->where('published', 2)
            ->update(['published' => 1]);
    }
    public function list()
    {
        $franchiseLabelField  = $this->getFranchiseLabelField();
        $collectionLabelField = $this->getCollectionLabelField();

        $categories = Category::orderBy('id')->get()->map(function ($cat) {
            $cat->label = $cat->{'title_' . Lang::locale()} ?? $cat->title_en;
            return $cat;
        });

        // КОЛЛЕКЦИИ: сортировка по ID DESC (новые сверху)
        $collections = Collection::with('category')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($col) use ($collectionLabelField) {
                $col->label = $col->{$collectionLabelField} ?? $col->label_en;
                return $col;
            });

        // ФРАНШИЗЫ: сортировка по ID DESC (новые сверху)
        $franchises = LocalizingFranchise::orderBy('id', 'desc')
            ->get()
            ->map(function ($f) use ($franchiseLabelField) {
                $f->label = $f->{$franchiseLabelField} ?? $f->label_en;
                return $f;
            });

        $franchiseCollections = [];

        $pivots1 = CollectionsFranchisesPivot::whereNotNull('collection_id')->get();
        foreach ($pivots1 as $p) {
            $frId  = (int) $p->id;
            $colId = (int) $p->collection_id;
            if (!isset($franchiseCollections[$frId])) {
                $franchiseCollections[$frId] = [];
            }
            if (!in_array($colId, $franchiseCollections[$frId])) {
                $franchiseCollections[$frId][] = $colId;
            }
        }

        $pivots2 = CollectionsCategoriesPivot::whereNotNull('franchise_id')
            ->select('collection_id', 'franchise_id')
            ->distinct()
            ->get();
        foreach ($pivots2 as $p) {
            $frId  = (int) $p->franchise_id;
            $colId = (int) $p->collection_id;
            if (!isset($franchiseCollections[$frId])) {
                $franchiseCollections[$frId] = [];
            }
            if (!in_array($colId, $franchiseCollections[$frId])) {
                $franchiseCollections[$frId][] = $colId;
            }
        }

        $collectionsCountMap = [];
        foreach ($franchiseCollections as $frId => $colIds) {
            $collectionsCountMap[$frId] = count($colIds);
        }

        $collectionFranchises = [];
        foreach ($franchiseCollections as $frId => $colIds) {
            foreach ($colIds as $colId) {
                $collectionFranchises[$colId] = ['franchise_id' => $frId];
            }
        }

        return response()->json([
            'categories'           => $categories,
            'collections'          => $collections,
            'franchises'           => $franchises,
            'collectionFranchises' => $collectionFranchises,
            'franchiseCollections' => $franchiseCollections,
            'collectionsCountMap'  => $collectionsCountMap,
        ]);
    }

    public function createCategory(Request $request)
    {
        $v = Validator::make($request->all(), [
            'value'    => 'required|string|max:10|unique:categories,value',
            'title_en' => 'required|string|max:64',
            'title_ru' => 'required|string|max:64',
            'index_id' => 'nullable|string|max:10',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();
            $cat = Category::create($request->only(['value', 'title_en', 'title_ru', 'index_id']));
            DB::commit();
            return response()->json(['success' => true, 'category' => $cat]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'value'    => 'required|string|max:10|unique:categories,value,' . $id,
            'title_en' => 'required|string|max:64',
            'title_ru' => 'required|string|max:64',
            'index_id' => 'nullable|string|max:10',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();
            $cat = Category::findOrFail($id);
            $cat->update($request->only(['value', 'title_en', 'title_ru', 'index_id']));
            DB::commit();
            return response()->json(['success' => true, 'category' => $cat]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            DB::beginTransaction();
            $cat = Category::findOrFail($id);

            if (Collection::where('category_id', $id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot delete category: collections still exist',
                ], 400);
            }

            $cat->delete();
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createCollection(Request $request)
    {
        $v = Validator::make($request->all(), [
            'value'       => 'required|string|max:64|unique:collections,value',
            'label_en'    => 'required|string|max:64',
            'label_ru'    => 'required|string|max:64',
            'category_id' => 'required|exists:categories,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();
            $col = Collection::create($request->only(['value', 'label_en', 'label_ru', 'category_id']));
            DB::commit();
            return response()->json(['success' => true, 'collection' => $col]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateCollection(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'value'       => 'required|string|max:64|unique:collections,value,' . $id,
            'label_en'    => 'required|string|max:64',
            'label_ru'    => 'required|string|max:64',
            'category_id' => 'required|exists:categories,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();
            $col = Collection::findOrFail($id);
            $col->update($request->only(['value', 'label_en', 'label_ru', 'category_id']));
            $this->resetMoviesPublished($col->id);
            DB::commit();
            return response()->json(['success' => true, 'collection' => $col]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCollection($id)
    {
        try {
            DB::beginTransaction();
            $col = Collection::findOrFail($id);

            if (CollectionsCategoriesPivot::where('collection_id', $id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot delete collection: it has assigned movies',
                ], 400);
            }
            $this->resetMoviesPublished($col->id);
            CollectionsFranchisesPivot::where('collection_id', $id)->delete();
            $col->delete();

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function createFranchise(Request $request)
    {
        $v = Validator::make($request->all(), [
            'label_en'     => 'required|string|max:64',
            'label_ru'     => 'required|string|max:64',
            'collectionId' => 'nullable|exists:collections,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();

            $value = preg_replace('/[^\p{L}\s]/u', '', $request->label_en);
            $value = preg_replace('/\s+/', ' ', $value);
            $value = strtolower(str_replace(' ', '_', trim($value)));

            if (LocalizingFranchise::where('value', $value)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'Franchise with this value already exists: ' . $value,
                ], 422);
            }

            $f = LocalizingFranchise::create([
                'value'    => $value,
                'label_en' => $request->label_en,
                'label_ru' => $request->label_ru,
            ]);

            if ($request->collectionId) {
                $collectionId = (int) $request->collectionId;
                $franchiseId  = (int) $f->id;

                CollectionsFranchisesPivot::firstOrCreate([
                    'id'            => $franchiseId,
                    'collection_id' => $collectionId,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'franchise' => $f]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateFranchise(Request $request, $id)
    {
        $v = Validator::make($request->all(), [
            'label_en' => 'required|string|max:64',
            'label_ru' => 'required|string|max:64',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();

            $f = LocalizingFranchise::findOrFail($id);

            $value = strtolower(str_replace(' ', '_', trim($request->label_en)));

            if (LocalizingFranchise::where('value', $value)->where('id', '!=', $id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'Franchise with this value already exists: ' . $value,
                ], 422);
            }

            $f->update([
                'value'    => $value,
                'label_en' => $request->label_en,
                'label_ru' => $request->label_ru,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'franchise' => $f]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteFranchise($id)
    {
        try {
            DB::beginTransaction();
            $f = LocalizingFranchise::findOrFail($id);

            $hasInCategoriesPivot = CollectionsCategoriesPivot::where('franchise_id', $id)->exists();
            $hasInFranchisesPivot = CollectionsFranchisesPivot::where('id', $id)->exists();

            if ($hasInCategoriesPivot || $hasInFranchisesPivot) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot delete franchise: it has assigned collections',
                ], 400);
            }

            $f->delete();
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function moveCollection(Request $request)
    {
        $v = Validator::make($request->all(), [
            'collectionId'     => 'required|exists:collections,id',
            'newCategoryId'    => 'required|exists:categories,id',
            'inheritFranchise' => 'required|boolean',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();

            $collection    = Collection::findOrFail($request->collectionId);
            $oldCategoryId = $collection->category_id;
            $newCategoryId = (int) $request->newCategoryId;

            if ($oldCategoryId === $newCategoryId) {
                DB::rollBack();
                return response()->json(['success' => false, 'error' => 'Collection is already in this section'], 400);
            }


            $collection->update(['category_id' => $newCategoryId]);
            $this->resetMoviesPublished($collection->id);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function assignFranchiseToCollection(Request $request)
    {
        $v = Validator::make($request->all(), [
            'collectionId' => 'required|exists:collections,id',
            'franchiseId'  => 'nullable|exists:franchises,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();

            $collectionId = $request->collectionId;
            $franchiseId  = $request->franchiseId;

            CollectionsFranchisesPivot::where('collection_id', $collectionId)->delete();
            if ($franchiseId) {
                CollectionsFranchisesPivot::firstOrCreate([
                    'id'            => $franchiseId,
                    'collection_id' => $collectionId,
                ]);
            }

            $this->resetMoviesPublished($collectionId);
            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncFranchiseCollections(Request $request)
    {
        $v = Validator::make($request->all(), [
            'franchiseId'     => 'required|exists:franchises,id',
            'collectionIds'   => 'required|array',
            'collectionIds.*' => 'exists:collections,id',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        try {
            DB::beginTransaction();

            $franchiseId      = (int) $request->franchiseId;
            $newCollectionIds = array_map('intval', $request->collectionIds);

            $currentCollectionIds = CollectionsCategoriesPivot::where('franchise_id', $franchiseId)
                ->distinct()
                ->pluck('collection_id')
                ->map(fn($v) => (int) $v)
                ->toArray();

            $toRemove = array_diff($currentCollectionIds, $newCollectionIds);
            $toAdd    = array_diff($newCollectionIds, $currentCollectionIds);

            foreach ($toRemove as $colId) {
                CollectionsFranchisesPivot::where('id', $franchiseId)
                    ->where('collection_id', $colId)
                    ->delete();
            }

            foreach ($toAdd as $colId) {

                CollectionsFranchisesPivot::firstOrCreate([
                    'id'            => $franchiseId,
                    'collection_id' => $colId,
                ]);
            }

            $affectedCollectionIds = array_unique(array_merge(array_values($toRemove), array_values($toAdd)));
            $this->resetMoviesPublished($affectedCollectionIds);

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ===================== Изображения коллекций =====================

    public function getCollectionImages()
    {
        $collectionsDir = 'collections';
        $result = [];

        if (!Storage::disk('public')->exists($collectionsDir)) {
            return response()->json($result);
        }

        $collectionDirs = Storage::disk('public')->directories($collectionsDir);

        foreach ($collectionDirs as $collectionDir) {
            $collectionId = basename($collectionDir);
            $coverPath = "collections/{$collectionId}/cover";
            $files = Storage::disk('public')->files($coverPath);

            if (!empty($files)) {
                $url = Storage::disk('public')->url($files[0]);
                $result[$collectionId] = ['cover' => $url];
            }
        }

        return response()->json($result);
    }

    public function updateCollectionImage(Request $request)
    {
        $request->validate([
            'collectionId' => 'required|integer|exists:collections,id',
            'cover'        => 'required|image|mimes:jpeg,png,gif|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $collectionId = $request->input('collectionId');
            $coverFile    = $request->file('cover');

            $coverPath = "collections/{$collectionId}/cover";

            if (Storage::disk('public')->exists($coverPath)) {
                Storage::disk('public')->deleteDirectory($coverPath);
            }

            $coverFilename = $coverFile->getClientOriginalName();
            $coverFile->storeAs($coverPath, $coverFilename, 'public');

            DB::commit();

            return response()->json([
                'success' => true,
                'url'     => Storage::disk('public')->url($coverPath . '/' . $coverFilename),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteCollectionImageManage(Request $request)
    {
        $data = $request->validate([
            'collectionId' => 'required|integer',
            'file'         => 'required|string',
        ]);

        try {
            $path = "collections/{$data['collectionId']}/cover/{$data['file']}";

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                return response()->json(['success' => true]);
            }

            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
