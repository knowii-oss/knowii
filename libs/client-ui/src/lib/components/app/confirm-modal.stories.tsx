import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { ConfirmModal } from './confirm-modal';

const Story: ComponentMeta<typeof ConfirmModal> = {
  component: ConfirmModal,
  title: 'ConfirmModal',
};
export default Story;

const Template: ComponentStory<typeof ConfirmModal> = (args) => <ConfirmModal {...args} />;

export const Primary = Template.bind({});
Primary.args = {
  title: 'Confirm?',
  isOpen: true,
  isDelete: false,
  description: 'Foo bar baz...',
};
