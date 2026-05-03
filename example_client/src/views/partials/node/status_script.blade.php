<script>
    function checkNodeStatus() {
        fetch('/admin/nodes/status')
            .then(res => res.json())
            .then(data => {
                for (const [id, info] of Object.entries(data)) {
                    const isOnline = info.status === 'Online';

                    // Elementos principais
                    const indicator = document.querySelector(`.node-indicator-${id}`);
                    const text = document.querySelector(`.node-text-${id}`);
                    const ping = document.querySelector(`.node-ping-${id}`);

                    // Novos elementos de Stats
                    const ram = document.querySelector(`.node-ram-${id}`);
                    const cpu = document.querySelector(`.node-cpu-${id}`);
                    const os = document.querySelector(`.node-os-${id}`);
                    const uptime = document.querySelector(`.node-uptime-${id}`);

                    if (indicator && text) {
                        if (isOnline) {
                            indicator.className = `relative w-2.5 h-2.5 rounded-full bg-cyan-500 shadow-[0_0_8px_#22d3ee] node-indicator-${id}`;
                            if(ping) ping.className = `absolute w-full h-full rounded-full bg-cyan-500 opacity-20 animate-ping node-ping-${id}`;
                            text.className = `text-[12px] font-bold text-cyan-400 uppercase tracking-widest node-text-${id}`;
                            text.textContent = 'Online';
                        } else {
                            indicator.className = `relative w-2.5 h-2.5 rounded-full bg-red-500 shadow-[0_0_8px_#ef4444] node-indicator-${id}`;
                            if(ping) ping.className = `absolute w-full h-full rounded-full bg-red-500 opacity-0 node-ping-${id}`;
                            text.className = `text-[12px] font-bold text-red-400 uppercase tracking-widest node-text-${id}`;
                            text.textContent = 'Offline';
                        }
                    }

                    // Atualiza os valores técnicos
                    if(ram) ram.textContent = info.ram;
                    if(cpu) cpu.textContent = info.cpu;
                    if(os) os.textContent = info.os;
                    if(uptime) uptime.textContent = info.uptime;
                }
            })
            .catch(err => console.error("Erro ao puxar o status: ", err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        checkNodeStatus();
        setInterval(checkNodeStatus, 5000);
    });
</script>