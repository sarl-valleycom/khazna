<?php

namespace App\Http\Controllers\Discord;

use App\Discord\Interaction;
use App\Discord\InteractionResponse;
use App\Discord\InteractionRouter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function __invoke(Request $request, InteractionRouter $router): JsonResponse
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json(['error' => 'Invalid interaction payload.'], 400);
        }

        $interaction = Interaction::fromArray($payload);

        if ($interaction->type() === Interaction::TYPE_PING) {
            return response()->json(InteractionResponse::pong()->toArray());
        }

        $configuredGuild = (string) config('discord.guild_id');
        $guildId = $interaction->guildId();

        // Buttons and modals sent from a DM (the correction reminder) never
        // carry a guild_id. The handlers only act for a linked employee on
        // their own sessions, so a guild-less component is allowed through;
        // slash commands and anything with a guild_id must match the server.
        $isDirectMessageComponent = $guildId === null && in_array(
            $interaction->type(),
            [Interaction::TYPE_COMPONENT, Interaction::TYPE_MODAL_SUBMIT],
            true,
        );

        if (! $isDirectMessageComponent
            && ($configuredGuild === '' || $guildId === null || ! hash_equals($configuredGuild, $guildId))) {
            return response()->json(['error' => 'This bot only works in the configured server.'], 403);
        }

        return response()->json($router->handle($interaction)->toArray());
    }
}
