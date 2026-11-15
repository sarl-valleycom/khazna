<?php

namespace App\Discord;

interface InteractionHandler
{
    public function handle(Interaction $interaction): InteractionResponse;
}
