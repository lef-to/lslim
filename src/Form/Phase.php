<?php

namespace LSlim\Form;

enum Phase: string
{
    case INPUT      = "input";
    case CONFIRM    = "confirm";
    case CONFIRMED  = "confirmed";
    case COMPLETE   = "complete";
}
