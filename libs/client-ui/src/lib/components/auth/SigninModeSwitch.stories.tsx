import type { ComponentStory, ComponentMeta } from '@storybook/react';
import { SigninModeSwitch } from './SigninModeSwitch';

const Story: ComponentMeta<typeof SigninModeSwitch> = {
  component: SigninModeSwitch,
  title: 'SigninModeSwitch',
};
export default Story;

const Template: ComponentStory<typeof SigninModeSwitch> = (args) => <SigninModeSwitch {...args} />;

export const Primary = Template.bind({});
Primary.args = {};
