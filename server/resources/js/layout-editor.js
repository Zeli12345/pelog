const root = document.getElementById('layout-editor');

if (root) {
    const canvas = document.getElementById('layout-canvas');
    const objectsLayer = document.getElementById('layout-objects');
    const selectionPanel = root.querySelector('[data-selection-panel]');
    const toolbar = root.querySelector('[data-toolbar]');
    const saveButton = root.querySelector('[data-action="save"]');
    const cancelButton = root.querySelector('[data-action="cancel"]');
    const editButton = root.querySelector('[data-action="toggle-edit"]');
    const dirtyBadge = root.querySelector('[data-dirty]');
    const hint = root.querySelector('[data-hint]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const deviceFilter = root.querySelector('[data-device-filter]');
    const statusFilter = root.querySelector('[data-status-filter]');
    const filterCount = root.querySelector('[data-filter-count]');

    const savedObjects = JSON.parse(document.getElementById('layout-data')?.textContent || '[]');
    const unplacedDevices = JSON.parse(document.getElementById('layout-unplaced')?.textContent || '[]');

    let objects = savedObjects.map((object) => ({
        ...object,
        key: `db-${object.id}`,
        is_visible: object.is_visible !== false,
    }));
    let editMode = false;
    let selectedKey = null;
    let dirty = false;
    let gridEnabled = true;
    let drag = null;
    let counter = 0;

    const clone = (value) => JSON.parse(JSON.stringify(value));
    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const snap = (value) => (gridEnabled ? Math.round(value) : Math.round(value * 10) / 10);
    const isDevice = (object) => object.type === 'device';
    const isCircle = (object) => object.type === 'table_circle';
    const objectLabel = (object) => object.label || object.device?.name || (isCircle(object) ? 'Meja bulat' : isDevice(object) ? 'Perangkat' : 'Meja');
    const nowKey = () => `tmp-${Date.now()}-${counter++}`;

    function matchesDeviceFilter(object) {
        if (!isDevice(object)) {
            return true;
        }

        const query = (deviceFilter?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || '';
        const name = `${object.device?.name || ''} ${object.device?.hostname || ''}`.toLowerCase();

        if (query !== '' && !name.includes(query)) {
            return false;
        }

        return status === '' || object.device?.status === status;
    }

    function applyUnplacedFilter() {
        let visible = 0;
        const query = (deviceFilter?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || '';

        root.querySelectorAll('[data-device-item]').forEach((item) => {
            const matches = (query === '' || (item.dataset.name || '').includes(query))
                && (status === '' || item.dataset.status === status);
            item.classList.toggle('hidden', !matches);
            if (matches) visible++;
        });

        if (filterCount) {
            if (query === '' && status === '') {
                filterCount.textContent = 'Menampilkan semua perangkat.';
            } else {
                const placed = objects.filter((object) => isDevice(object)
                    && object.is_visible !== false
                    && matchesDeviceFilter(object)).length;
                filterCount.textContent = `${placed} di denah · ${visible} belum ditempatkan cocok.`;
            }
        }
    }

    function applyFilter() {
        render();
        applyUnplacedFilter();
    }

    deviceFilter?.addEventListener('input', applyFilter);
    statusFilter?.addEventListener('change', applyFilter);

    function markDirty() {
        dirty = true;
        dirtyBadge?.classList.remove('hidden');
        if (saveButton) saveButton.disabled = false;
    }

    function markClean() {
        dirty = false;
        dirtyBadge?.classList.add('hidden');
        if (saveButton) saveButton.disabled = true;
    }

    function setHint(text) {
        if (hint) hint.textContent = text;
    }

    function findObject(key) {
        return objects.find((object) => object.key === key) || null;
    }

    function statusClass(status) {
        if (status === 'in_use') return 'bg-moss-500';
        if (status === 'maintenance') return 'bg-gold-500';
        if (status === 'offline') return 'bg-ink-faint';
        return 'bg-navy-500';
    }

    function createElement(object) {
        const element = document.createElement('div');
        element.className = 'absolute select-none';
        element.dataset.key = object.key;
        element.style.left = `${object.x}%`;
        element.style.top = `${object.y}%`;
        element.style.width = `${object.w}%`;
        element.style.height = `${object.h}%`;
        element.style.zIndex = String(object.z_index || (isDevice(object) ? 20 : 5));
        element.style.transform = object.rotation ? `rotate(${object.rotation}deg)` : '';
        element.style.touchAction = 'none';

        if (object.is_visible === false && editMode) {
            element.style.opacity = '0.35';
            element.style.borderStyle = 'dashed';
        } else if (!matchesDeviceFilter(object)) {
            element.style.opacity = '0.15';
        }

        if (isDevice(object)) {
            element.className += ' cursor-pointer rounded-md border-2 border-navy-700 bg-navy-800/95 px-1 py-0.5 text-white shadow-sm';
            if (editMode) element.className += ' cursor-grab active:cursor-grabbing';
            const content = document.createElement('div');
            content.className = 'flex h-full min-h-0 flex-col justify-center overflow-hidden text-center';
            const title = document.createElement('span');
            title.className = 'truncate text-[9px] font-semibold leading-tight sm:text-[10px]';
            title.textContent = objectLabel(object);
            const subtitle = document.createElement('span');
            subtitle.className = 'flex items-center justify-center gap-1 truncate text-[7px] text-navy-200 sm:text-[8px]';
            const dot = document.createElement('span');
            dot.className = `h-1 w-1 rounded-full ${statusClass(object.device?.status)}`;
            const host = document.createElement('span');
            host.textContent = object.device?.hostname || '';
            subtitle.append(dot, host);
            content.append(title, subtitle);
            element.append(content);
        } else {
            element.className += ' flex items-center justify-center border-2 shadow-sm ';
            element.className += isCircle(object) ? 'rounded-full ' : 'rounded-md ';
            element.style.backgroundColor = object.color || '#F3E3B5';
            element.style.borderColor = object.color || '#C9A227';
            const label = document.createElement('span');
            label.className = 'pointer-events-none max-w-full truncate px-1 text-center text-[8px] font-semibold text-navy-900 sm:text-[10px]';
            label.textContent = objectLabel(object);
            element.append(label);
        }

        if (selectedKey === object.key) {
            element.style.outline = '3px solid #C9A227';
            element.style.outlineOffset = '1px';
        }

        if (editMode) {
            const handle = document.createElement('span');
            handle.dataset.resize = '1';
            handle.className = 'absolute -bottom-1 -right-1 h-3 w-3 cursor-nwse-resize rounded-sm border border-white bg-navy-900';
            handle.style.zIndex = '5';
            element.append(handle);
        }

        element.addEventListener('pointerdown', (event) => {
            if (!editMode || event.target.dataset.resize) return;
            event.stopPropagation();
            selectedKey = object.key;
            renderSelection();
            const rect = canvas.getBoundingClientRect();
            drag = {
                mode: 'move',
                key: object.key,
                startX: event.clientX,
                startY: event.clientY,
                original: { ...object },
                rect,
            };
            canvas.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        });

        element.querySelector('[data-resize]')?.addEventListener('pointerdown', (event) => {
            if (!editMode) return;
            event.stopPropagation();
            selectedKey = object.key;
            renderSelection();
            const rect = canvas.getBoundingClientRect();
            drag = {
                mode: 'resize',
                key: object.key,
                startX: event.clientX,
                startY: event.clientY,
                original: { ...object },
                rect,
            };
            canvas.setPointerCapture?.(event.pointerId);
            event.preventDefault();
        });

        if (isDevice(object) && !editMode && object.device?.href) {
            element.addEventListener('click', (event) => {
                event.stopPropagation();
                window.location.href = object.device.href;
            });
        }

        return element;
    }

    function render() {
        objectsLayer.replaceChildren();
        objects
            .slice()
            .sort((a, b) => (a.z_index || 0) - (b.z_index || 0))
            .filter((object) => (editMode || object.is_visible !== false)
                && (editMode || matchesDeviceFilter(object)))
            .forEach((object) => objectsLayer.append(createElement(object)));
        renderSelection();
    }

    function renderSelection() {
        if (!selectionPanel) return;
        selectionPanel.replaceChildren();
        const object = selectedKey ? findObject(selectedKey) : null;

        if (!object) {
            const empty = document.createElement('p');
            empty.className = 'text-ink-faint';
            empty.textContent = editMode ? 'Klik objek untuk mengubah label, warna, atau ukuran.' : 'Klik perangkat untuk membuka detailnya.';
            selectionPanel.append(empty);
            return;
        }

        const title = document.createElement('p');
        title.className = 'mb-2 text-xs font-semibold text-ink';
        title.textContent = isDevice(object) ? 'Perangkat' : isCircle(object) ? 'Meja Bulat' : 'Meja Kotak';
        selectionPanel.append(title);

        if (isDevice(object) && object.device?.href) {
            const link = document.createElement('a');
            link.href = object.device.href;
            link.className = 'mb-2 inline-block text-[11px] font-semibold text-navy-800 hover:underline';
            link.textContent = 'Buka detail perangkat →';
            selectionPanel.append(link);
        }

        if (editMode) {
            const labelWrap = document.createElement('label');
            labelWrap.className = 'mt-2 block text-[11px] font-medium text-ink-soft';
            labelWrap.textContent = 'Label';
            const input = document.createElement('input');
            input.className = 'input mt-1 text-xs';
            input.value = object.label || '';
            input.placeholder = objectLabel(object);
            input.addEventListener('input', () => {
                object.label = input.value.trim() || null;
                markDirty();
                renderObjectsOnly();
            });
            labelWrap.append(input);
            selectionPanel.append(labelWrap);

            if (!isDevice(object)) {
                const colorWrap = document.createElement('label');
                colorWrap.className = 'mt-2 block text-[11px] font-medium text-ink-soft';
                colorWrap.textContent = 'Warna';
                const color = document.createElement('input');
                color.type = 'color';
                color.value = object.color || '#F3E3B5';
                color.className = 'mt-1 h-8 w-full rounded border border-line';
                color.addEventListener('input', () => {
                    object.color = color.value;
                    markDirty();
                    renderObjectsOnly();
                });
                colorWrap.append(color);
                selectionPanel.append(colorWrap);
            }

            const actions = document.createElement('div');
            actions.className = 'mt-3 flex flex-wrap gap-1.5';

            const visibility = document.createElement('button');
            visibility.type = 'button';
            visibility.className = 'btn-secondary !px-2 !py-1 text-[10px]';
            visibility.textContent = object.is_visible === false ? 'Tampilkan di denah' : 'Sembunyikan dari denah';
            visibility.addEventListener('click', () => {
                object.is_visible = object.is_visible === false;
                markDirty();
                render();
            });
            actions.append(visibility);

            const rotate = document.createElement('button');
            rotate.type = 'button';
            rotate.className = 'btn-secondary !px-2 !py-1 text-[10px]';
            rotate.textContent = 'Putar 90°';
            rotate.addEventListener('click', () => {
                object.rotation = ((object.rotation || 0) + 90) % 360;
                markDirty();
                render();
            });
            actions.append(rotate);

            if (isDevice(object)) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-secondary !px-2 !py-1 text-[10px] text-brick-600';
                remove.textContent = 'Lepas dari denah';
                remove.addEventListener('click', () => {
                    objects = objects.filter((candidate) => candidate.key !== object.key);
                    selectedKey = null;
                    markDirty();
                    render();
                });
                actions.append(remove);
            } else {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-secondary !px-2 !py-1 text-[10px] text-brick-600';
                remove.textContent = 'Hapus meja';
                remove.addEventListener('click', () => {
                    objects = objects.filter((candidate) => candidate.key !== object.key);
                    selectedKey = null;
                    markDirty();
                    render();
                });
                actions.append(remove);
            }

            selectionPanel.append(actions);
        }
    }

    function renderObjectsOnly() {
        const selected = selectedKey;
        selectedKey = selected;
        objectsLayer.replaceChildren();
        objects
            .slice()
            .sort((a, b) => (a.z_index || 0) - (b.z_index || 0))
            .forEach((object) => objectsLayer.append(createElement(object)));
    }

    function addFurniture(type) {
        const size = type === 'table_circle' ? 14 : 18;
        const object = {
            key: nowKey(),
            id: null,
            type,
            device_id: null,
            label: type === 'table_circle' ? 'Meja bulat' : 'Meja',
            x: snap(38),
            y: snap(38),
            w: size,
            h: type === 'table_circle' ? size : 10,
            rotation: 0,
            color: type === 'table_circle' ? '#DCEBE3' : '#F3E3B5',
            z_index: 5,
            is_visible: true,
        };
        objects.push(object);
        selectedKey = object.key;
        markDirty();
        render();
        setHint('Meja ditambahkan. Geser dan atur ukurannya.');
    }

    function addDevice(device) {
        if (objects.some((object) => object.type === 'device' && Number(object.device_id) === Number(device.id))) {
            return;
        }

        const object = {
            key: nowKey(),
            id: null,
            type: 'device',
            device_id: Number(device.id),
            label: null,
            x: snap(8),
            y: snap(8),
            w: 12,
            h: 8,
            rotation: 0,
            color: null,
            z_index: 20,
            is_visible: true,
            device: {
                name: device.name,
                hostname: device.hostname,
                status: device.status,
                href: device.href,
            },
        };
        objects.push(object);
        selectedKey = object.key;
        markDirty();
        render();
        setHint('Perangkat ditempatkan. Geser ke posisi yang diinginkan.');
    }

    function autoLayout() {
        // Perangkat yang belum ditempatkan ikut ditata otomatis.
        unplacedDevices.forEach((device) => {
            if (objects.some((object) => isDevice(object) && Number(object.device_id) === Number(device.id))) {
                return;
            }

            objects.push({
                key: nowKey(),
                id: null,
                type: 'device',
                device_id: Number(device.id),
                label: null,
                x: 8,
                y: 8,
                w: 12,
                h: 8,
                rotation: 0,
                color: null,
                z_index: 20,
                is_visible: true,
                device: {
                    name: device.name,
                    hostname: device.hostname,
                    status: device.status,
                    href: device.href,
                },
            });
        });

        const devices = objects.filter(isDevice);
        if (devices.length === 0) {
            window.alert('Belum ada perangkat untuk ditata. Tambahkan perangkat dari panel “Belum Ditempatkan”.');
            return;
        }

        const columns = Math.min(4, Math.max(2, Math.ceil(Math.sqrt(devices.length))));
        const width = 12;
        const height = 8;
        const gapX = 3;
        const gapY = 5;
        const startX = 5;
        const startY = 8;
        const maxRows = Math.max(1, Math.floor((100 - startY) / (height + gapY)));

        devices.forEach((object, index) => {
            const column = index % columns;
            const row = Math.floor(index / columns) % maxRows;
            object.w = width;
            object.h = height;
            object.x = snap(Math.min(100 - width, startX + column * (width + gapX)));
            object.y = snap(Math.min(100 - height, startY + row * (height + gapY)));
            object.rotation = 0;
        });

        markDirty();
        render();
        setHint('Perangkat disusun otomatis. Geser bila perlu, lalu simpan.');
    }

    function beginDrag(event, mode) {
        if (!editMode || !drag) return;
        const rect = drag.rect;
        const dx = ((event.clientX - drag.startX) / rect.width) * 100;
        const dy = ((event.clientY - drag.startY) / rect.height) * 100;
        const object = findObject(drag.key);
        if (!object) return;

        if (mode === 'move') {
            object.x = snap(clamp(drag.original.x + dx, 0, 100 - object.w));
            object.y = snap(clamp(drag.original.y + dy, 0, 100 - object.h));
        } else {
            object.w = snap(clamp(drag.original.w + dx, 4, 100 - object.x));
            object.h = snap(clamp(drag.original.h + dy, 4, 100 - object.y));
            if (isCircle(object)) {
                object.w = snap(clamp(object.w, 4, Math.min(100 - object.x, 100 - object.y)));
                object.h = object.w;
            }
        }

        markDirty();
        renderObjectsOnly();
    }

    function endDrag() {
        if (!drag) return;
        drag = null;
        render();
    }

    canvas.addEventListener('pointermove', (event) => {
        if (drag) beginDrag(event, drag.mode);
    });
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);

    canvas.addEventListener('pointerdown', (event) => {
        if (!editMode || event.target !== canvas && event.target.id !== 'layout-grid') return;
        selectedKey = null;
        render();
    });

    root.querySelector('[data-action="toggle-edit"]')?.addEventListener('click', () => {
        editMode = !editMode;
        toolbar?.classList.toggle('hidden', !editMode);
        toolbar?.classList.toggle('flex', editMode);
        saveButton?.classList.toggle('hidden', !editMode);
        cancelButton?.classList.toggle('hidden', !editMode);
        if (editButton) editButton.textContent = editMode ? 'Selesai' : 'Atur Layout';
        setHint(editMode ? 'Tarik objek untuk memindahkan; tarik sudut untuk mengubah ukuran.' : 'Klik perangkat untuk membuka detailnya.');
        selectedKey = null;
        render();
    });

    root.querySelector('[data-action="add-table-rect"]')?.addEventListener('click', () => addFurniture('table_rect'));
    root.querySelector('[data-action="add-table-circle"]')?.addEventListener('click', () => addFurniture('table_circle'));
    root.querySelector('[data-action="auto-layout"]')?.addEventListener('click', autoLayout);
    root.querySelector('[data-action="select"]')?.addEventListener('click', () => {
        selectedKey = null;
        render();
    });
    root.querySelector('[data-action="toggle-grid"]')?.addEventListener('click', (event) => {
        gridEnabled = !gridEnabled;
        event.currentTarget.classList.toggle('bg-navy-900', gridEnabled);
        event.currentTarget.classList.toggle('text-white', gridEnabled);
        document.getElementById('layout-grid').classList.toggle('hidden', !gridEnabled);
    });

    cancelButton?.addEventListener('click', () => {
        if (dirty && !window.confirm('Batalkan perubahan layout?')) return;
        window.location.reload();
    });

    saveButton?.addEventListener('click', async () => {
        saveButton.disabled = true;
        saveButton.textContent = 'Menyimpan…';

        try {
            const response = await fetch(root.dataset.saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    version: Number(root.dataset.version),
                    objects: objects.map((object) => ({
                        type: object.type,
                        device_id: object.type === 'device' ? Number(object.device_id) : null,
                        label: object.label,
                        x: Number(object.x),
                        y: Number(object.y),
                        w: Number(object.w),
                        h: Number(object.h),
                        rotation: Number(object.rotation || 0),
                        color: object.color,
                        z_index: Number(object.z_index || 0),
                        is_visible: object.is_visible !== false,
                    })),
                }),
            });

            if (response.status === 409) {
                window.alert('Layout sudah diubah admin lain. Halaman akan dimuat ulang.');
                window.location.reload();
                return;
            }

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Gagal menyimpan layout.');
            }

            root.dataset.version = String(data.version);
            markClean();
            setHint('Layout tersimpan.');
            window.location.reload();
        } catch (error) {
            window.alert(error.message || 'Gagal menyimpan layout.');
            saveButton.disabled = false;
        } finally {
            saveButton.textContent = 'Simpan Layout';
        }
    });

    root.querySelectorAll('[data-place-device]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!editMode) {
                window.alert('Klik “Atur Layout” terlebih dahulu.');
                return;
            }
            const device = unplacedDevices.find((item) => Number(item.id) === Number(button.dataset.placeDevice));
            if (!device) return;
            addDevice(device);
            button.closest('div')?.remove();
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    markClean();
    render();
    applyUnplacedFilter();
}
