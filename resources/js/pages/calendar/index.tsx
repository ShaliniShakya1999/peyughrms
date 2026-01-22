import { PageTemplate } from '@/components/page-template';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import type { EventClickArg } from '@fullcalendar/core';
import timeGridPlugin from '@fullcalendar/timegrid';
import axios from 'axios';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CalendarEvent {
    id: string;
    title: string;
    start: string | Date;
    end: string | Date;
    type: 'meeting' | 'holiday' | 'leave' | 'weekoff' | 'working';
    allDay?: boolean;
}

interface CalendarProps {
    events: CalendarEvent[];
    canEditWeekoff: boolean; // HR + Admin only
}

export default function CalendarIndex({ events, canEditWeekoff }: CalendarProps) {
    const { t } = useTranslation();
    const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const handleEventClick = (clickInfo: EventClickArg) => {
        const event = events.find((e) => e.id === clickInfo.event.id);
        if (!event) return;

        // Everyone can view event details; edit actions are restricted
        setSelectedEvent(event);
        setIsDialogOpen(true);
    };

    const toggleWeekoff = (makeWorking: boolean) => {
        // Double check - Employee ko edit allow mat karo
        if (!canEditWeekoff || !selectedEvent) {
            alert('You do not have permission to edit weekoff days.');
            return;
        }

        const url = makeWorking ? '/calendar/cancel-weekoff' : '/calendar/restore-weekoff';
        const dateStr = new Date(selectedEvent.start).toISOString().slice(0, 10);

        axios.post(url, {
            date: dateStr,
        })
        .then((response) => {
            console.log('Weekoff toggle success:', response.data);
            setIsDialogOpen(false);
            window.location.reload();
        })
        .catch((error) => {
            console.error('Weekoff toggle error:', error);
            if (error.response?.status === 403) {
                alert('You do not have permission to edit weekoff days. Only HR and Admin can edit.');
            } else {
                alert('Failed to update weekoff. Please try again.');
            }
        });
    };

    return (
        <PageTemplate title={t('Calendar')} description="" url="/calendar">
            <div className="rounded-lg bg-white p-6 shadow">
                <div className="mb-4 flex gap-4">
                    <div className="flex items-center gap-2 text-sm">
                        <div className="h-3 w-3 rounded bg-blue-500"></div> {t('Meetings')}
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <div className="h-3 w-3 rounded bg-green-500"></div> {t('Holidays')}
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <div className="h-3 w-3 rounded bg-yellow-500"></div> {t('Leaves')}
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                        <div className="h-3 w-3 rounded bg-red-500"></div> {t('Week Off (2nd & 4th Saturday)')}
                    </div>
                </div>

                <div style={{ height: '600px' }}>
                    <FullCalendar
                        plugins={[dayGridPlugin, timeGridPlugin, interactionPlugin]}
                        initialView="dayGridMonth"
                        headerToolbar={{
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay',
                        }}
                        events={events}
                        height="100%"
                        eventClick={handleEventClick}
                    />
                </div>
            </div>

            {/* Dialog: Everyone can view; only HR/Admin can edit */}
            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{selectedEvent?.title}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <Badge>
                            {selectedEvent?.type === 'weekoff' && t('Week Off')}
                            {selectedEvent?.type === 'working' && t('Working Day')}
                            {selectedEvent?.type === 'holiday' && t('Holiday')}
                            {selectedEvent?.type === 'leave' && t('Leave')}
                            {selectedEvent?.type === 'meeting' && t('Meeting')}
                        </Badge>

                        <div>
                            <p className="text-muted-foreground text-sm">{t('Date')}</p>
                            <p className="font-medium">
                                {selectedEvent?.start && new Date(selectedEvent.start).toDateString()}
                            </p>
                        </div>

                        {/* Edit buttons only for HR/Admin */}
                        {canEditWeekoff && selectedEvent?.type === 'weekoff' && (
                            <button
                                className="w-full rounded bg-red-600 py-2 text-white hover:bg-red-700"
                                onClick={() => toggleWeekoff(true)}
                            >
                                Make this a Working Day
                            </button>
                        )}

                        {canEditWeekoff && selectedEvent?.type === 'working' && (
                            <button
                                className="w-full rounded bg-green-600 py-2 text-white hover:bg-green-700"
                                onClick={() => toggleWeekoff(false)}
                            >
                                Mark as Week Off Again
                            </button>
                        )}

                        {/* Read-only message for Employee/User */}
                        {!canEditWeekoff && (selectedEvent?.type === 'weekoff' || selectedEvent?.type === 'working') && (
                            <p className="text-sm text-muted-foreground text-center">
                                {t('You can view this information but cannot make changes.')}
                            </p>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
