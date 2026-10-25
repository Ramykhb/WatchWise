<?php

namespace App\MovieNight\Controllers;

use App\Auth\CurrentUser\CurrentUser;
use App\Models\MovieNight;
use App\MovieNight\FormRequests\InviteGuestsRequest;
use App\MovieNight\FormRequests\RsvpRequest;
use App\MovieNight\FormRequests\StoreMovieNightRequest;
use App\MovieNight\FormRequests\UpdateMovieNightRequest;
use App\MovieNight\FormRequests\VoteRequest;
use App\MovieNight\InterfaceServices\MovieNightServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Movie nights the user hosts or is invited to: guests, RSVPs, the shortlist and votes. */
class MovieNightController
{
    public function __construct(private readonly MovieNightServiceInterface $nights) {}

    /** GET /api/movie-nights — upcoming, pending invitations and past nights. */
    public function index(CurrentUser $current): JsonResponse
    {
        return response()->json($this->nights->overview($current->user()));
    }

    /** POST /api/movie-nights — invites people by @username and seeds the shortlist. */
    public function store(StoreMovieNightRequest $request, CurrentUser $current): JsonResponse
    {
        return response()->json(['night' => $this->nights->create($request->toDto(), $current->user())], 201);
    }

    /** GET /api/movie-nights/{night} */
    public function show(MovieNight $night, CurrentUser $current): JsonResponse
    {
        Gate::authorize('view', $night);

        return response()->json(['night' => $this->nights->show($night, $current->user())]);
    }

    /** POST /api/movie-nights/{night}/invites — the host invites more people by @username. */
    public function invite(InviteGuestsRequest $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        return response()->json(['night' => $this->nights->invite($night, $request->toDto(), $current->user())]);
    }

    /** PUT /api/movie-nights/{night}/rsvp — an invited guest answers. */
    public function rsvp(RsvpRequest $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        return response()->json(['night' => $this->nights->rsvp($night, $request->toDto(), $current->user())]);
    }

    /** PUT /api/movie-nights/{night}/vote — one vote per person; adds the film to the shortlist if new. */
    public function vote(VoteRequest $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        return response()->json(['night' => $this->nights->vote($night, $request->toDto(), $current->user())]);
    }

    /** PATCH /api/movie-nights/{night} — the host picks the winner, cancels or edits details. */
    public function update(UpdateMovieNightRequest $request, MovieNight $night, CurrentUser $current): JsonResponse
    {
        return response()->json(['night' => $this->nights->update($night, $request->toDto(), $current->user())]);
    }
}
