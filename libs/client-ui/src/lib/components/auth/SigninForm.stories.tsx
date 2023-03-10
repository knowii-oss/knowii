import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SigninForm } from './SigninForm';

const Story: ComponentMeta<typeof SigninForm> = {
  component: SigninForm,
  title: 'SigninForm',
};
export default Story;

const Template: ComponentStory<typeof SigninForm> = (args) => <SigninForm {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
