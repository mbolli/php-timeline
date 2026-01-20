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
};

window.timeline = timeline;

export { timeline };
