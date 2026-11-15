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

        // A DM with the bot has no guild_id: commands, buttons and modals sent
        // there are routed, and the handlers only act for a linked employee.
        // Anything that does come from a server must be the configured one.
        if ($guildId !== null && ($configuredGuild === '' || ! hash_equals($configuredGuild, $guildId))) {
            return response()->json(['error' => 'This bot only works in the configured server.'], 403);
        }

        return response()->json($router->handle($interaction)->toArray());
    }
}
