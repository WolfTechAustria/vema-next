{{--
    Unterschriften aus den Vereinseinstellungen.
    $rightAlignSecond: zweite Unterschrift rechtsbündig (Klasse .signature-right).
--}}
@php
    $signatories = app(\App\Services\ClubBranding::class)->signatories();
@endphp

@if(count($signatories) > 0)
    <table class="signatures">

        <tr>

            @foreach($signatories as $signatory)

                <td @class(['signature-right' => ($rightAlignSecond ?? false) && $loop->index === 1])>

                    {{ $signatory['title'] }}<br>

                    @if($signatory['imagePath'])
                        <img
                            class="signature-image"
                            src="{{ $signatory['imagePath'] }}"
                        >
                    @endif

                    <br>

                    @if($signatory['name'] !== '')
                        ({{ $signatory['name'] }})
                    @endif

                </td>

            @endforeach

        </tr>

    </table>
@endif
