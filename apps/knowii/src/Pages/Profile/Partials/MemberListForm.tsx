import { router } from '@inertiajs/react';
import { knowiiApiClient } from '@knowii/common';
import ActionSection from '@/Components/ActionSection';
import { SaveStatus, Switch, useInstantSave } from '@/Components/ui';

/**
 * The words of the section (#1466)
 */
export const MEMBER_LIST_WORDS = {
  title: 'Member list',
  description: 'Who finds you in the member list of your communities.',
  label: 'Show me in the member list of my communities',
  help: 'When this is off, the other members do not find you in the member list, the member search or the mention suggestions. The owner, the admins and the moderators who manage members still see you, and you stay in the list of a community where you are the owner, an admin or a moderator. This is not a privacy wall: members still see your posts and your profile, and can still mention you or send you a message from your profile. Your online status has its own switch.',
};

interface Props {
  showInMemberList: boolean;
}

/**
 * Show or hide the member in the member list of their communities (#1466). A switch, saved at once, with its status
 * (#1569), in the form of OnlineStatusForm. Hiding stops discovery: the member page, the posts and a typed mention do not
 * change.
 */
export default function MemberListForm({ showInMemberList }: Props) {
  const setting = useInstantSave(showInMemberList);

  const change = async (show: boolean) => {
    const result = await setting.save(show, () => knowiiApiClient.users.updateMemberListVisibility(show));
    if (!result?.ok) {
      return;
    }
    router.reload({ only: ['auth'] });
  };

  return (
    <ActionSection title={MEMBER_LIST_WORDS.title} description={MEMBER_LIST_WORDS.description}>
      <Switch
        checked={setting.value}
        busy={setting.busy}
        onChange={(show) => void change(show)}
        label={MEMBER_LIST_WORDS.label}
        description={MEMBER_LIST_WORDS.help}
        status={<SaveStatus state={setting.saveState} />}
      />
    </ActionSection>
  );
}
