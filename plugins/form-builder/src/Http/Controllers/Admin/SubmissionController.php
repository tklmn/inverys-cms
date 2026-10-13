<?php

namespace Plugins\FormBuilder\Http\Controllers\Admin;

use App\Helpers\SearchHelper;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Plugins\FormBuilder\Models\Form;
use Plugins\FormBuilder\Models\FormSubmission;
use Plugins\FormBuilder\Services\SubmissionExporter;

#[Middleware('permission:show-form-submission', only: ['index', 'show', 'export'])]
#[Middleware('permission:edit-form-submission', only: ['toggleRead'])]
#[Middleware('permission:delete-form-submission', only: ['destroy'])]
class SubmissionController extends Controller
{
    public function index(Request $request, Form $form): View
    {
        $submissions = $form->submissions()
            ->when($request->has('is_read') && $request->is_read !== '', function ($query) use ($request) {
                $query->where('is_read', $request->boolean('is_read'));
            })
            ->when($request->search, function ($query, $search) {
                $search = SearchHelper::escapeLike($search);
                $query->where('data', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('form-builder-admin::submissions.index', [
            'form' => $form,
            'submissions' => $submissions,
        ]);
    }

    public function show(Form $form, FormSubmission $submission): View
    {
        $this->ensureSubmissionBelongsToForm($form, $submission);

        return view('form-builder-admin::submissions.show', [
            'form' => $form,
            'submission' => $submission,
        ]);
    }

    public function toggleRead(Form $form, FormSubmission $submission)
    {
        $this->ensureSubmissionBelongsToForm($form, $submission);

        $submission->update([
            'is_read' => ! $submission->is_read,
            'read_at' => ! $submission->is_read ? now() : null,
        ]);

        return redirect()->back()->with('success',
            $submission->is_read
                ? __('form-builder::forms.mark_read')
                : __('form-builder::forms.mark_unread')
        );
    }

    public function destroy(Form $form, FormSubmission $submission)
    {
        $this->ensureSubmissionBelongsToForm($form, $submission);

        $submission->delete();

        return redirect()
            ->route('backend.forms.submissions.index', $form)
            ->with('success', __('form-builder::forms.submission_deleted'));
    }

    /**
     * The {form}/{submission} route bindings are not scoped, so a submission from
     * another form could be addressed under any form's URL. Reject the mismatch.
     */
    private function ensureSubmissionBelongsToForm(Form $form, FormSubmission $submission): void
    {
        abort_unless($submission->form_id === $form->id, 404);
    }

    public function export(Form $form)
    {
        $submissions = $form->submissions()->orderByDesc('created_at')->get();

        return SubmissionExporter::export($form, $submissions);
    }
}
