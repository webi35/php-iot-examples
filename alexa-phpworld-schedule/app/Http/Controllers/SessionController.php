<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Develpr\AlexaApp\Facades\Alexa;
use Develpr\AlexaApp\Request\AlexaRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function getSessions(AlexaRequest $request)
    {
        $request->getIntent();
        $time = $request->slot('time') ?: Carbon::now()->format('G:i');
        $day = $request->slot('day') ?: Carbon::now()->format('Y-m-d');
        $room = $request->slot('room');

        try {
            $sessions = $this->findSessions($time, $day, $room);

            if (count($sessions) === 0) {
                // No sessions found

                return Alexa::say('Sorry, I couldn\'t find any sessions on ' . $day . ' at ' . $time)->endSession();
                return Alexa::say('Sorry, I couldn\'t find any sessions at that time.');
            } elseif (count($sessions) === 1) {
                return Alexa::say($this->getSessionText(reset($sessions)))->endSession();;
            } else {
                $text = 'I found ' . count($sessions) . ' sessions. ';
                foreach ($sessions as $session) {
                    $text .= $this->getSessionText($session) . ' ';
                }

                return Alexa::say($text)->endSession();;
            }
        } catch (\Exception $ex) {
            return Alexa::say('Sorry, I\'m having trouble searching for sessions right now.')->endSession();;
        }
    }

    /**
     * @param string|null $time
     * @param string|null $day
     * @param string|null $room
     *
     * @return array
     *
     * @todo: Refactor into a service
     */
    private function findSessions($time, $day, $room)
    {
        if (empty($day)) {
            $day = Carbon::now()->format('Y-m-d');
        }

        if (empty($time)) {
            $time = Carbon::now()->format('H:i');
        } elseif (strlen($time) === 2) {
            // We can get a time indicator for certain utterances like "morning",
            // so we'll need to convert those to a certain time for our purposes
            //
            switch ($time) {
                case 'MO':
                    $time = '08:00';
                    break;
                case 'AF':
                    $time = '12:30';
                    break;
                case 'EV':
                    $time = '';
                    break;
                case 'NI':
                    $time = '';
                    break;
            }
        }

        $timestamp = Carbon::parse($day.' '.$time, 'America/New_York')->getTimestamp();

        /** @var Builder $query */
        $query = DB::table('sessions');
        $query->where('start', '<=', $timestamp);
        $query->where('end', '>', $timestamp);

        if (!empty($room)) {
            $query->where('room', '=', $room);
        }

        $query->orderBy('start');

        return $query->get();
    }

    /**
     * @param \stdClass $session
     *
     * @return string
     */
    private function getSessionText($session)
    {
        $speakers = implode(' and ', explode('|', $session->speakers));

        $time = Carbon::createFromTimestamp($session->start, 'America/New_York')->format('g:i');

        if (empty($speakers)) {
            return sprintf('%s will be in %s at %s', $session->title, $session->room, $time);
        }

        return sprintf('%s will be giving a talk entitled %s in %s at %s.', $speakers, $session->title, $session->room, $time);
    }
}
