import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { ForgotPasswordForm } from './ForgotPasswordForm';

const Story: ComponentMeta<typeof ForgotPasswordForm> = {
  component: ForgotPasswordForm,
  title: 'ForgotPasswordForm',
};
export default Story;

const Template: ComponentStory<typeof ForgotPasswordForm> = (args) => <ForgotPasswordForm {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
