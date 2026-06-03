<!DOCTYPE html>
<html><head><title>Print</title></head>
<body>
<p>ITEM RECEIPT SLIP</p>
<p>ITEM RELEASE SLIP</p>
<p>{{ $transaction->item_name_snapshot }}</p>
<p>{{ $transaction->received_from }}</p>
<p>{{ $transaction->ris_iar_number }}</p>
<p>{{ $transaction->released_to_office }}</p>
<p>{{ $transaction->receiver_name }}</p>
</body></html>
