import { NotificationSoundPreset, UserSetting } from '@/types';

let sharedAudioContext: AudioContext | null = null;
let audioUnlocked = false;

function ensureAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext;
    if (!AudioCtx) {
        return null;
    }

    if (!sharedAudioContext) {
        sharedAudioContext = new AudioCtx();
    }

    if (sharedAudioContext.state === 'suspended') {
        sharedAudioContext.resume().catch(() => {});
    }

    return sharedAudioContext;
}

export function installAudioUnlockListener(): void {
    if (typeof window === 'undefined' || audioUnlocked) {
        return;
    }

    const unlock = () => {
        const ctx = ensureAudioContext();
        if (ctx && ctx.state === 'running') {
            audioUnlocked = true;
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
        }
    };

    window.addEventListener('pointerdown', unlock, { passive: true });
    window.addEventListener('keydown', unlock, { passive: true });
}

function playToneSequence(
    ctx: AudioContext,
    notes: { freq: number; start: number; duration: number; type?: OscillatorType }[],
    gainLevel: number
): void {
    const now = ctx.currentTime;

    for (const note of notes) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = note.type || 'sine';
        osc.frequency.setValueAtTime(note.freq, now + note.start);

        gain.gain.setValueAtTime(0.0001, now + note.start);
        gain.gain.exponentialRampToValueAtTime(Math.max(0.001, gainLevel), now + note.start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + note.start + note.duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now + note.start);
        osc.stop(now + note.start + note.duration + 0.03);
    }
}

export function useNotificationSound() {
    const playPreset = (preset: NotificationSoundPreset = 'chime', volumePercent: number = 70): void => {
        const clampedVolume = Math.max(0, Math.min(100, volumePercent));
        if (clampedVolume === 0) {
            return;
        }

        const ctx = ensureAudioContext();
        if (!ctx) {
            return;
        }

        const masterGain = (clampedVolume / 100) * 0.35;

        if (preset === 'bell') {
            playToneSequence(
                ctx,
                [
                    { freq: 783.99, start: 0, duration: 0.45, type: 'triangle' },
                    { freq: 1046.5, start: 0.16, duration: 0.65, type: 'sine' },
                ],
                masterGain
            );
            return;
        }

        if (preset === 'soft_ping') {
            playToneSequence(
                ctx,
                [{ freq: 587.33, start: 0, duration: 0.35, type: 'sine' }],
                masterGain
            );
            return;
        }

        if (preset === 'digital_alarm') {
            playToneSequence(
                ctx,
                [
                    { freq: 880, start: 0, duration: 0.11, type: 'square' },
                    { freq: 880, start: 0.15, duration: 0.11, type: 'square' },
                    { freq: 1174.66, start: 0.32, duration: 0.22, type: 'square' },
                ],
                masterGain * 0.65
            );
            return;
        }

        // Default 'chime'
        playToneSequence(
            ctx,
            [
                { freq: 523.25, start: 0, duration: 0.22, type: 'sine' },
                { freq: 659.25, start: 0.14, duration: 0.24, type: 'sine' },
                { freq: 783.99, start: 0.28, duration: 0.42, type: 'sine' },
            ],
            masterGain
        );
    };

    const playForUserSetting = (setting?: UserSetting | null): void => {
        if (!setting) {
            playPreset('chime', 70);
            return;
        }

        if (!setting.notifications_enabled || !setting.notification_sound_enabled) {
            return;
        }

        playPreset(setting.notification_sound || 'chime', setting.notification_volume ?? 70);
    };

    return {
        playPreset,
        playForUserSetting,
        installAudioUnlockListener,
    };
}

