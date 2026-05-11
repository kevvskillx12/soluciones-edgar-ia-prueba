<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{ __("You're logged in!") }}

                    <!-- 🤖 IA INTEGRADA -->
                    <div style="margin-top:20px; padding:20px; background:#4f46e5; color:white; border-radius:10px;">

                        <h3>🤖 Asistente IA</h3>

                        <textarea id="prompt"
                            style="width:100%; margin-top:10px; color:black;"></textarea>

                        <button onclick="sendAI()"
                            style="margin-top:10px; background:white; color:#4f46e5; padding:8px;">
                            Enviar
                        </button>

                        <div id="respuesta" style="margin-top:10px;"></div>

                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- ⚡ SCRIPT DE IA -->
    <script>
    async function sendAI() {
        const prompt = document.getElementById('prompt').value;

        const res = await fetch('/ia-test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ pregunta: prompt })
        });

        const data = await res.json();

        document.getElementById('respuesta').innerText = data.respuesta;
    }
    </script>

</x-app-layout>