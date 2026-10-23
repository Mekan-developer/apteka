<?php

namespace App\Http\Controllers;

use App\Actions\Synonyms\CreateDrugSynonymAction;
use App\Actions\Synonyms\DeleteDrugSynonymAction;
use App\Actions\Synonyms\ListDrugSynonymsAction;
use App\Http\Requests\Synonyms\StoreDrugSynonymRequest;
use App\Http\Resources\DrugSynonymResource;
use App\Models\DrugSynonym;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DrugSynonymController extends Controller
{
    /**
     * Show the names that the search treats as one drug.
     */
    public function index(ListDrugSynonymsAction $listSynonyms): Response
    {
        return Inertia::render('synonyms/Index', [
            'synonyms' => DrugSynonymResource::collection($listSynonyms->handle())->resolve(),
        ]);
    }

    /**
     * Link two names of one drug; the search uses the link right away.
     */
    public function store(StoreDrugSynonymRequest $request, CreateDrugSynonymAction $createSynonym): RedirectResponse
    {
        $synonym = $createSynonym->handle($request->string('term')->toString(), $request->string('synonym')->toString());

        notify('success', __('messages.synonym_created', ['term' => $synonym->term, 'synonym' => $synonym->synonym]));

        return to_route('synonyms.index');
    }

    /**
     * Remove a link between two names.
     */
    public function destroy(DrugSynonym $synonym, DeleteDrugSynonymAction $deleteSynonym): RedirectResponse
    {
        $deleteSynonym->handle($synonym);

        notify('success', __('messages.synonym_deleted', ['term' => $synonym->term, 'synonym' => $synonym->synonym]));

        return to_route('synonyms.index');
    }
}
