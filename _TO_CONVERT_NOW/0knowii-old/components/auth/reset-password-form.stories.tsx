import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { ResetPasswordForm } from './reset-password-form';

const Story: ComponentMeta<typeof ResetPasswordForm> = {
  component: ResetPasswordForm,
  title: 'ResetPasswordForm',
};
export default Story;

const Template: ComponentStory<typeof ResetPasswordForm> = (args) => <ResetPasswordForm {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
