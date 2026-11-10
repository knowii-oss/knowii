import { useMemo, useState } from 'react';
import type { Author, Conversation } from '@knowii/common';
import {
  knowiiApiClient,
  MAX_CONVERSATION_PARTICIPANTS,
  MAX_LENGTH_CHAT_MESSAGE,
  newConversationFormSchema,
  useAppData,
} from '@knowii/common';
import { FaPaperPlane } from 'react-icons/fa6';
import InputError from '@/Components/InputError';
import { useMentionSource } from '@/Components/Members/use-mention-source';
import { useImageUploader } from '@/Components/Uploads/use-image-uploader';
import { Button, Dialog, MarkdownEditor } from '@/Components/ui';
import { MemberPicker } from './MemberPicker';

export interface NewConversationDialogProps {
  open: boolean;
  communityCuid: string;
  currentUserCuid: string;
  // Members chosen when the dialog opens ("Send a message" on a member page)
  initialMembers?: Author[];
  onClose: () => void;
  onStarted: (conversation: Conversation) => void;
}

/**
 * Write to one or more members of the community (#1186): choose them, write the first message, send. Writing again to
 * the same single member continues the conversation with them.
 */
export function NewConversationDialog({
  open,
  communityCuid,
  currentUserCuid,
  initialMembers,
  onClose,
  onStarted,
}: NewConversationDialogProps) {
  const { showToast } = useAppData();
  // The parent gives the dialog a new key each time it opens: the state starts again from these values
  const [members, setMembers] = useState<Author[]>(initialMembers ?? []);
  const [body, setBody] = useState('');
  const [errors, setErrors] = useState<{ participants?: string | undefined; body?: string | undefined }>({});
  const [sending, setSending] = useState(false);
  const mentions = useMentionSource(communityCuid);
  const images = useImageUploader(communityCuid);
  const renderPreview = useMemo(
    () => (markdown: string, signal: AbortSignal) => knowiiApiClient.markdown.preview(markdown, signal, communityCuid),
    [communityCuid],
  );

  const send = async () => {
    const parsed = newConversationFormSchema.safeParse({ participant_cuids: members.map((member) => member.cuid), body });
    if (!parsed.success) {
      const issues = parsed.error.issues;
      setErrors({
        participants: issues.find((issue) => issue.path[0] === 'participant_cuids')?.message,
        body: issues.find((issue) => issue.path[0] === 'body')?.message,
      });
      return;
    }
    setSending(true);
    const result = await knowiiApiClient.communities.conversations.start(communityCuid, parsed.data);
    setSending(false);
    if (!result.ok) {
      setErrors({ participants: result.errors['participant_cuids']?.[0], body: result.errors['body']?.[0] });
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    onStarted(result.data);
  };

  return (
    <Dialog
      open={open}
      onClose={sending ? () => undefined : onClose}
      size="lg"
      title="New message"
      description="Only the members of the conversation read it."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={sending}>
            Cancel
          </Button>
          <Button onClick={() => void send()} loading={sending}>
            <FaPaperPlane aria-hidden="true" />
            Send
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div>
          <MemberPicker
            id="new-conversation-members"
            label="To"
            communityCuid={communityCuid}
            value={members}
            onChange={setMembers}
            excludeCuids={[currentUserCuid]}
            max={MAX_CONVERSATION_PARTICIPANTS - 1}
            autoFocus={(initialMembers ?? []).length === 0}
          />
          <InputError className="mt-2" message={errors.participants} />
        </div>
        <div>
          <label htmlFor="new-conversation-body" className="block text-sm font-bold text-primary">
            Message
          </label>
          <MarkdownEditor
            id="new-conversation-body"
            value={body}
            onChange={setBody}
            renderPreview={renderPreview}
            mentions={mentions}
            images={images}
            rows={4}
            maxLength={MAX_LENGTH_CHAT_MESSAGE}
            autoFocus={(initialMembers ?? []).length > 0}
            invalid={!!errors.body}
          />
          <InputError className="mt-2" message={errors.body} />
        </div>
      </div>
    </Dialog>
  );
}
