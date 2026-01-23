// Timeline utility functions exposed to window for Datastar
// Functions return values; signal updates happen in data-on:* attributes

import '@mbolli/datastar-attribute-on-keys';

declare global {
    interface Window {
        timeline: typeof timeline;
    }
}

interface WheelResult {
    zoom: number;
    panX: number;
}

interface ResizeState {
    itemId: number;
    groupId: number;
    handle: 'start' | 'end';
    originalStartDate: string;
    originalEndDate: string;
    originalLeft: number;
    originalWidth: number;
    startX: number;
    itemElement: HTMLElement;
    tooltip: HTMLElement | null;
}

let resizeState: ResizeState | null = null;
let justFinishedResize = false;

const timeline = {
    /**
     * Handle wheel zoom - returns new zoom and panX values
     * Only zooms when Ctrl/Cmd is held, returns null if not zooming
     */
    handleWheel(e: WheelEvent, currentZoom: number, currentPanX: number): WheelResult | null {
        if (!e.ctrlKey && !e.metaKey) return null;
        
        e.preventDefault();
        
        const delta = e.deltaY > 0 ? 0.9 : 1.1;
        const newZoom = Math.max(0.1, Math.min(10, currentZoom * delta));
        
        // Zoom towards mouse position
        const container = e.currentTarget as HTMLElement;
        const rect = container.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const zoomRatio = newZoom / currentZoom;
        
        const newPanX = mouseX - (mouseX - currentPanX) * zoomRatio;
        
        return { zoom: newZoom, panX: newPanX };
    },

    /**
     * Calculate pan delta from drag
     */
    getDragDelta(e: MouseEvent, lastMouseX: number): number {
        return e.clientX - lastMouseX;
    },

    /**
     * Get touch X position
     */
    getTouchX(e: TouchEvent): number {
        return e.touches[0]?.clientX ?? 0;
    },

    /**
     * Check if event target is a timeline item (should not drag)
     */
    isTimelineItem(e: MouseEvent | TouchEvent): boolean {
        return !!(e.target as HTMLElement).closest('.timeline-item');
    },

    /**
     * Start resizing a timeline item - returns true if resize started
     */
    startResize(e: MouseEvent): boolean {
        const handle = e.target as HTMLElement;
        if (!handle.classList.contains('resize-handle')) return false;

        e.stopPropagation();
        e.preventDefault();

        const item = handle.closest('.timeline-item') as HTMLElement;
        if (!item) return false;

        const handleType = handle.dataset.handle as 'start' | 'end';
        const itemId = parseInt(item.dataset.itemId || '0', 10);
        const groupId = parseInt(item.dataset.groupId || '0', 10);

        item.classList.add('resizing');
        
        // Set cursor on body to prevent flickering when mouse moves off handle
        document.body.style.cursor = 'ew-resize';

        // Create tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'resize-tooltip';
        document.body.appendChild(tooltip);

        // Store original values for calculating deltas
        const originalLeft = parseFloat(item.style.getPropertyValue('--item-left') || '0');
        const originalWidth = parseFloat(item.style.getPropertyValue('--item-width') || '0');

        resizeState = {
            itemId,
            groupId,
            handle: handleType,
            originalStartDate: item.dataset.startDate || '',
            originalEndDate: item.dataset.endDate || '',
            originalLeft,
            originalWidth,
            startX: e.clientX,
            itemElement: item,
            tooltip,
        };

        this.updateResizeTooltip(e.clientX, e.clientY);
        return true;
    },

    /**
     * Handle resize mouse move
     */
    handleResizeMove(e: MouseEvent): void {
        if (!resizeState) return;

        e.preventDefault();

        const zoom = this.getCurrentZoom();
        const monthWidth = 20 * zoom;
        const deltaX = e.clientX - resizeState.startX;
        const deltaMonths = Math.round(deltaX / monthWidth);

        const item = resizeState.itemElement;
        const { originalLeft, originalWidth } = resizeState;

        if (resizeState.handle === 'start') {
            // Moving start handle - calculate from original values
            const newLeft = Math.max(0, originalLeft + (deltaMonths * 20));
            const newWidth = Math.max(20, originalWidth - (deltaMonths * 20));
            
            // Calculate new start date
            const newStartDate = this.calculateDateFromPosition(newLeft);
            
            // Only update if the width stays positive and dates are valid
            if (newWidth >= 20 && this.isValidDateOrder(newStartDate, resizeState.originalEndDate || this.getTodayDate())) {
                item.style.setProperty('--item-left', String(newLeft));
                item.style.setProperty('--item-width', String(newWidth));
                item.dataset.startDate = newStartDate;
            }
        } else {
            // Moving end handle - calculate from original values
            const newWidth = Math.max(20, originalWidth + (deltaMonths * 20));
            const newEndDate = this.calculateDateFromPosition(originalLeft + newWidth - 20);
            
            if (newWidth >= 20 && this.isValidDateOrder(resizeState.originalStartDate, newEndDate)) {
                item.style.setProperty('--item-width', String(newWidth));
                item.dataset.endDate = newEndDate;
            }
        }

        this.updateResizeTooltip(e.clientX, e.clientY);
    },

    /**
     * Finish resizing and save - returns true if resize was active
     */
    async finishResize(): Promise<boolean> {
        if (!resizeState) return false;

        const { itemId, groupId, itemElement, tooltip, originalStartDate, originalEndDate } = resizeState;
        const newStartDate = itemElement.dataset.startDate || originalStartDate;
        const newEndDate = itemElement.dataset.endDate || originalEndDate;

        itemElement.classList.remove('resizing');
        tooltip?.remove();

        // Check if dates actually changed
        const startChanged = newStartDate !== originalStartDate;
        const endChanged = newEndDate !== originalEndDate;

        if (startChanged || endChanged) {
            // Send update to server
            try {
                const formData = new FormData();
                formData.append('startDate', newStartDate);
                formData.append('endDate', newEndDate);
                formData.append('groupId', String(groupId));

                await fetch(`/cmd/items/${itemId}/resize`, {
                    method: 'PATCH',
                    body: formData,
                });
            } catch (err) {
                console.error('Failed to save resize:', err);
                // Revert visual changes on error
                itemElement.dataset.startDate = originalStartDate;
                itemElement.dataset.endDate = originalEndDate;
            }
        }

        resizeState = null;
        document.body.style.cursor = '';
        
        // Set flag to prevent click event from firing
        justFinishedResize = true;
        setTimeout(() => { justFinishedResize = false; }, 200);
        
        return true;
    },

    /**
     * Cancel resize operation - returns true if resize was active
     */
    cancelResize(): boolean {
        if (!resizeState) return false;

        const { itemElement, tooltip, originalStartDate, originalEndDate, originalLeft, originalWidth } = resizeState;
        
        // Restore original position and dates
        itemElement.style.setProperty('--item-left', String(originalLeft));
        itemElement.style.setProperty('--item-width', String(originalWidth));
        itemElement.dataset.startDate = originalStartDate;
        itemElement.dataset.endDate = originalEndDate;
        
        itemElement.classList.remove('resizing');
        tooltip?.remove();
        resizeState = null;
        document.body.style.cursor = '';
        
        // Set flag to prevent click event from firing
        justFinishedResize = true;
        setTimeout(() => { justFinishedResize = false; }, 200);
        
        return true;
    },

    /**
     * Check if we just finished resizing (to prevent click)
     */
    wasResizing(): boolean {
        return justFinishedResize;
    },

    /**
     * Update tooltip position and content
     */
    updateResizeTooltip(x: number, y: number): void {
        if (!resizeState?.tooltip || !resizeState.itemElement) return;

        const startDate = resizeState.itemElement.dataset.startDate || '';
        const endDate = resizeState.itemElement.dataset.endDate || 'ongoing';

        resizeState.tooltip.textContent = `${startDate} → ${endDate}`;
        resizeState.tooltip.style.left = `${x + 12}px`;
        resizeState.tooltip.style.top = `${y - 30}px`;
    },

    /**
     * Calculate date from pixel position
     */
    calculateDateFromPosition(position: number): string {
        const startYear = this.getStartYear();
        const monthWidth = 20; // Base month width without zoom
        const totalMonths = Math.round(position / monthWidth);
        
        const year = startYear + Math.floor(totalMonths / 12);
        const month = (totalMonths % 12) + 1;
        
        return `${year}-${String(month).padStart(2, '0')}`;
    },

    /**
     * Check if start date is before or equal to end date
     */
    isValidDateOrder(startDate: string, endDate: string): boolean {
        if (!endDate) return true; // No end date is valid (ongoing)
        return startDate <= endDate;
    },

    /**
     * Get current zoom level
     */
    getCurrentZoom(): number {
        const wrapper = document.querySelector('.timeline-wrapper') as HTMLElement;
        if (wrapper) {
            const zoomStr = getComputedStyle(wrapper).getPropertyValue('--zoom-level');
            return parseFloat(zoomStr) || 1;
        }
        return 1;
    },

    /**
     * Check if currently resizing
     */
    isResizing(): boolean {
        return resizeState !== null;
    },

    /**
     * Calculate new panX for scrolling to a date
     */
    getPanXForDate(dateStr: string, zoom: number): number {
        const [year, month] = dateStr.split('-').map(Number);
        const startYear = this.getStartYear();
        const monthWidth = 20 * zoom;
        const monthsFromStart = ((year - startYear) * 12) + (month - 1);
        
        const container = document.querySelector('.timeline-wrapper');
        const containerWidth = container?.clientWidth ?? 800;
        
        return -(monthsFromStart * monthWidth) + (containerWidth / 2);
    },

    /**
     * Get today's date string
     */
    getTodayDate(): string {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    },

    /**
     * Get the start year from the timeline
     */
    getStartYear(): number {
        const yearMarker = document.querySelector('.year-marker');
        if (yearMarker) {
            return parseInt(yearMarker.getAttribute('data-year') || '2000', 10);
        }
        return 2000;
    },

    /**
     * Clamp zoom value
     */
    clampZoom(zoom: number): number {
        return Math.max(0.1, Math.min(10, zoom));
    },

    /**
     * Start dragging a group track
     */
    startDragGroup(e: DragEvent, groupId: number): void {
        if (!e.dataTransfer) return;
        
        e.dataTransfer.setData('text/plain', String(groupId));
        e.dataTransfer.effectAllowed = 'move';
        
        // Use the whole track as the drag image
        const handle = e.target as HTMLElement;
        const track = handle.closest('.track') as HTMLElement;
        if (track) {
            track.classList.add('dragging');
            // Set drag image to the track-label for better visual
            const label = track.querySelector('.track-label') as HTMLElement;
            if (label) {
                e.dataTransfer.setDragImage(label, 10, label.offsetHeight / 2);
            }
        }
    },

    /**
     * End dragging a group track
     */
    endDragGroup(e: DragEvent): void {
        const handle = e.target as HTMLElement;
        const track = handle.closest('.track');
        track?.classList.remove('dragging');
    },

    /**
     * Handle dragover on a track
     */
    dragOverTrack(e: DragEvent): void {
        if (!e.dataTransfer) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        const track = (e.currentTarget as HTMLElement);
        track.classList.add('drag-over');
    },

    /**
     * Handle dragleave on a track
     */
    dragLeaveTrack(e: DragEvent): void {
        const track = (e.currentTarget as HTMLElement);
        track.classList.remove('drag-over');
    },

    /**
     * Handle drop on a track - reorder groups
     */
    async dropOnTrack(e: DragEvent, targetGroupId: number): Promise<void> {
        e.preventDefault();
        
        const track = (e.currentTarget as HTMLElement);
        track.classList.remove('drag-over');
        
        const draggedId = e.dataTransfer?.getData('text/plain');
        if (!draggedId || draggedId === String(targetGroupId)) return;
        
        // Get all track IDs in current order
        const tracks = [...document.querySelectorAll('.track')] as HTMLElement[];
        const ids = tracks.map(t => t.dataset.groupId!);
        
        // Reorder: remove from old position, insert at new position
        const fromIdx = ids.indexOf(draggedId);
        const toIdx = ids.indexOf(String(targetGroupId));
        
        if (fromIdx === -1 || toIdx === -1) return;
        
        ids.splice(fromIdx, 1);
        ids.splice(toIdx, 0, draggedId);
        
        // Send to server
        try {
            await fetch('/cmd/groups/reorder', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderedIds: ids.map(Number) }),
            });
        } catch (err) {
            console.error('Failed to reorder groups:', err);
        }
    },
};

window.timeline = timeline;

export { timeline };
