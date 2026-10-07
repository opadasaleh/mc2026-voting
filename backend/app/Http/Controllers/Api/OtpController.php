<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Otp\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /events/{event}/auth/otp/request and /verify (behind the on-site gate).
 */
class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function request(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $result = $this->otp->request($event, trim($data['full_name']), $this->phone($data['phone']), $request->ip());

        return response()->json(['data' => $result], 202);
    }

    public function verify(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return response()->json(['data' => $this->otp->verify($event, $this->phone($data['phone']), $data['code'], $request->ip())]);
    }

    private function phone(string $input): string
    {
        return PhoneNumber::normalize($input)
            ?? throw ValidationException::withMessages(['phone' => 'Enter a valid mobile number.']);
    }
}
