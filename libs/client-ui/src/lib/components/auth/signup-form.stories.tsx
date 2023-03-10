import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SignupForm } from './signup-form';

const Story: ComponentMeta<typeof SignupForm> = {
  component: SignupForm,
  title: 'SignupForm',
};
export default Story;

const Template: ComponentStory<typeof SignupForm> = (args) => <SignupForm {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
