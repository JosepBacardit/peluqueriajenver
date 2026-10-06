{{-- Live total of the checked services (progressive enhancement, plain
     JavaScript): sums data-minutes of every ".service-checkbox" and writes
     "<label>: 1 h 25 min" into #$targetId, hiding it when nothing is
     checked. Without JavaScript the page shows the server's total instead
     (or none, on the public step 1). Used by /reservas and by the panel's
     appointment forms (review finding L6).

     @param string $targetId
     @param string $label --}}
<script>
    (function () {
        var boxes = document.querySelectorAll('.service-checkbox');
        var target = document.getElementById(@json($targetId));

        if (!target) return;

        function formatMinutes(minutes) {
            var hours = Math.floor(minutes / 60), rest = minutes % 60;
            if (hours === 0) return rest + ' min';
            if (rest === 0) return hours + ' h';
            return hours + ' h ' + rest + ' min';
        }

        function update() {
            var totalMinutes = 0, count = 0;
            boxes.forEach(function (box) {
                if (box.checked) {
                    totalMinutes += parseInt(box.dataset.minutes, 10);
                    count++;
                }
            });
            if (count > 0) {
                target.textContent = @json($label) + ': ' + formatMinutes(totalMinutes);
                target.classList.remove('hidden');
            } else {
                target.classList.add('hidden');
            }
        }

        boxes.forEach(function (box) { box.addEventListener('change', update); });
        update();
    })();
</script>
