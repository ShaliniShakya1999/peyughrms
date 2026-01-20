import { PageTemplate } from '@/components/page-template';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
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
    canManage: boolean; // true only for Admin
}

export default function CalendarIndex({ events, canManage }: CalendarProps) {
    const { t } = useTranslation();
    const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const handleEventClick = (clickInfo: any) => {
        const event = events.find((e) => e.id === clickInfo.event.id);
        if (!event) return;

        // Sirf Admin ko weekoff/working ka dialog khulega
        if (!canManage && (event.type === 'weekoff' || event.type === 'working')) {
            return;
        }

        setSelectedEvent(event);
        setIsDialogOpen(true);
    };

    const toggleWeekoff = (makeWorking: boolean) => {
        if (!canManage || !selectedEvent) return;

        const url = makeWorking ? '/calendar/cancel-weekoff' : '/calendar/restore-weekoff';

        axios.post(url, {
            date: new Date(selectedEvent.start).toISOString().slice(0, 10),
        }).then(() => {
            setIsDialogOpen(false);
            window.location.reload();
        });
    };

    return (
        <PageTemplate title={t('Calendar')} url="/calendar">
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

            {/* Dialog sirf Admin ke liye render hoga */}
            {canManage && (
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

                            {selectedEvent?.type === 'weekoff' && (
                                <button
                                    className="w-full rounded bg-red-600 py-2 text-white"
                                    onClick={() => toggleWeekoff(true)}
                                >
                                    Make this a Working Day
                                </button>
                            )}

                            {selectedEvent?.type === 'working' && (
                                <button
                                    className="w-full rounded bg-green-600 py-2 text-white"
                                    onClick={() => toggleWeekoff(false)}
                                >
                                    Mark as Week Off Again
                                </button>
                            )}
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </PageTemplate>
    );
}
